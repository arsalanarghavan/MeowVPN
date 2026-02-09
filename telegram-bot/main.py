import asyncio
import os
import logging
from typing import Optional
from aiogram import Bot, Dispatcher, types, F
from aiogram.filters import Command, StateFilter
from aiogram.fsm.context import FSMContext
from aiogram.fsm.state import State, StatesGroup
from aiogram.types import (
    ReplyKeyboardMarkup, KeyboardButton,
    InlineKeyboardMarkup, InlineKeyboardButton,
    CallbackQuery
)
import aiohttp
import redis.asyncio as redis
from datetime import datetime
import qrcode
from io import BytesIO

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Configuration
BOT_TOKEN = os.getenv('TELEGRAM_BOT_TOKEN')
API_BASE_URL = os.getenv('API_BASE_URL', 'http://laravel:8000')
# Public URL for subscription links (must be reachable by end users; e.g. https://sub.example.com)
SUBSCRIPTION_PUBLIC_URL = os.getenv('SUBSCRIPTION_PUBLIC_URL', '').rstrip('/') or API_BASE_URL
REDIS_HOST = os.getenv('REDIS_HOST', 'redis')
REDIS_PORT = int(os.getenv('REDIS_PORT', 6379))
REDIS_PASSWORD = os.getenv('REDIS_PASSWORD')

# Initialize
bot = Bot(token=BOT_TOKEN)
dp = Dispatcher()

# Redis connection pool
redis_pool = None

async def get_redis():
    global redis_pool
    if redis_pool is None:
        redis_pool = redis.ConnectionPool(
            host=REDIS_HOST,
            port=REDIS_PORT,
            password=REDIS_PASSWORD,
            decode_responses=True
        )
    return redis.Redis(connection_pool=redis_pool)

# States
class PurchaseStates(StatesGroup):
    selecting_plan = State()
    selecting_location = State()
    confirming = State()

class DepositStates(StatesGroup):
    entering_amount = State()
    selecting_gateway = State()
    uploading_proof = State()

class AdminStates(StatesGroup):
    broadcasting = State()
    approving_transaction = State()

def _subscriptions_list(response) -> list:
    """Normalize subscriptions API response: backend may return array or { data: [] }."""
    if response is None:
        return []
    if isinstance(response, list):
        return response
    return (response or {}).get('data', [])


# API Helper
async def api_request(method: str, endpoint: str, data: dict = None, headers: dict = None, timeout: int = 30):
    """Make API request to Laravel backend"""
    url = f"{API_BASE_URL}/api/{endpoint}"
    try:
        async with aiohttp.ClientSession() as session:
            async with session.request(method, url, json=data, headers=headers, timeout=timeout) as response:
                if response.status == 200 or response.status == 201:
                    return await response.json()
                else:
                    error_text = await response.text()
                    logger.error(f"API error {response.status}: {error_text}")
                    return None
    except Exception as e:
        logger.error(f"API request failed: {e}")
        return None


async def api_request_deposit_with_proof(amount_rials: int, proof_image_bytes: bytes, token: str, timeout: int = 30):
    """POST to transactions/deposit with multipart form (amount, gateway, proof_image file)."""
    url = f"{API_BASE_URL}/api/transactions/deposit"
    form = aiohttp.FormData()
    form.add_field('amount', str(amount_rials))
    form.add_field('gateway', 'card_to_card')
    form.add_field('proof_image', proof_image_bytes, filename='proof.jpg', content_type='image/jpeg')
    headers = {'Authorization': f'Bearer {token}'}
    try:
        async with aiohttp.ClientSession() as session:
            async with session.post(url, data=form, headers=headers, timeout=timeout) as response:
                if response.status in (200, 201):
                    return await response.json()
                error_text = await response.text()
                logger.error(f"API deposit with proof error {response.status}: {error_text}")
                return None
    except Exception as e:
        logger.error(f"Deposit with proof request failed: {e}")
        return None

# Token Management
TOKEN_CACHE_TTL = 86400  # 24 hours - tokens last longer in cache

async def get_user_token(telegram_id: int, username: str = None) -> Optional[str]:
    """Get user token from cache or authenticate/register via API"""
    r = await get_redis()
    cache_key = f"user_token:{telegram_id}"
    token = await r.get(cache_key)
    
    if token:
        # Verify token is still valid by making a request
        test_response = await api_request('GET', 'auth/me', headers={'Authorization': f'Bearer {token}'})
        if test_response:
            return token
        # Token invalid, remove from cache
        await r.delete(cache_key)
    
    # Try to authenticate existing user via register endpoint (returns existing user with new token)
    try:
        register_data = {'telegram_id': telegram_id}
        if username:
            register_data['username'] = username
            
        response = await api_request('POST', 'auth/register', register_data)
        
        if response:
            token = response.get('token')
            if token:
                # Cache for 24 hours
                await r.setex(cache_key, TOKEN_CACHE_TTL, token)
                return token
    except Exception as e:
        logger.error(f"Token retrieval failed: {e}")
    
    return None

async def refresh_user_token(telegram_id: int, username: str = None) -> Optional[str]:
    """Force refresh user token"""
    r = await get_redis()
    cache_key = f"user_token:{telegram_id}"
    
    # Remove cached token
    await r.delete(cache_key)
    
    # Get new token
    return await get_user_token(telegram_id, username)

async def get_user_data(telegram_id: int) -> Optional[dict]:
    """Get user data from API"""
    token = await get_user_token(telegram_id)
    if not token:
        return None
    
    response = await api_request('GET', 'auth/me', headers={'Authorization': f'Bearer {token}'})
    
    # If token expired during the request, try to refresh
    if not response:
        token = await refresh_user_token(telegram_id)
        if token:
            response = await api_request('GET', 'auth/me', headers={'Authorization': f'Bearer {token}'})
    
    return response

# Keyboards
def get_main_keyboard():
    """Main menu keyboard"""
    return ReplyKeyboardMarkup(
        keyboard=[
            [KeyboardButton(text="🛍 خرید سرویس"), KeyboardButton(text="🔧 سرویس‌های من")],
            [KeyboardButton(text="👤 پروفایل و کیف پول"), KeyboardButton(text="🍏 آموزش اتصال")],
            [KeyboardButton(text="📞 پشتیبانی"), KeyboardButton(text="🧪 تست رایگان")],
        ],
        resize_keyboard=True
    )

def get_admin_keyboard():
    """Admin menu keyboard"""
    return ReplyKeyboardMarkup(
        keyboard=[
            [KeyboardButton(text="📊 آمار سریع"), KeyboardButton(text="💳 تایید تراکنش")],
            [KeyboardButton(text="📢 ارسال همگانی"), KeyboardButton(text="🔙 بازگشت")],
        ],
        resize_keyboard=True
    )

def get_reseller_keyboard():
    """Reseller menu keyboard"""
    return ReplyKeyboardMarkup(
        keyboard=[
            [KeyboardButton(text="📊 آمار زیرمجموعه"), KeyboardButton(text="👥 کاربران من")],
            [KeyboardButton(text="🛒 سرویس‌های زیرمجموعه"), KeyboardButton(text="💳 تراکنش‌های زیرمجموعه")],
            [KeyboardButton(text="🔗 لینک بازاریابی"), KeyboardButton(text="🔙 بازگشت")],
        ],
        resize_keyboard=True
    )

def get_profile_keyboard():
    """Profile menu keyboard"""
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text="💰 شارژ کیف پول", callback_data="deposit")],
        [InlineKeyboardButton(text="📜 تاریخچه تراکنش", callback_data="transaction_history")],
        [InlineKeyboardButton(text="🔗 لینک دعوت", callback_data="referral_link")],
    ])

def get_service_keyboard(subscription_id: int):
    """Service actions keyboard"""
    return InlineKeyboardMarkup(inline_keyboard=[
        [
            InlineKeyboardButton(text="🔗 دریافت لینک", callback_data=f"get_link:{subscription_id}"),
            InlineKeyboardButton(text="📱 QR Code", callback_data=f"get_qr:{subscription_id}")
        ],
        [
            InlineKeyboardButton(text="🔄 تمدید", callback_data=f"renew:{subscription_id}"),
            InlineKeyboardButton(text="🌍 تغییر لوکیشن", callback_data=f"change_location:{subscription_id}")
        ],
        [InlineKeyboardButton(text="🔙 بازگشت", callback_data="back_to_services")],
    ])

def get_gateway_keyboard():
    """Payment gateway selection keyboard"""
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text="💳 پرداخت آنلاین (زیبال)", callback_data="gateway:zibal")],
        [InlineKeyboardButton(text="🏦 کارت به کارت", callback_data="gateway:card_to_card")],
        [InlineKeyboardButton(text="🔙 انصراف", callback_data="cancel_deposit")],
    ])

# Handlers
@dp.message(Command("start"))
async def cmd_start(message: types.Message, state: FSMContext):
    """Handle /start command"""
    await state.clear()
    
    args = message.text.split()[1:] if len(message.text.split()) > 1 else []
    referrer_id = args[0] if args else None
    
    register_data = {'telegram_id': message.from_user.id, 'username': message.from_user.username}
    if referrer_id and isinstance(referrer_id, str) and referrer_id.isdigit() and int(referrer_id) > 0:
        register_data['parent_id'] = int(referrer_id)
    
    await api_request('POST', 'auth/register', register_data)
    
    await message.answer(
        "👋 به MeowVPN خوش آمدید!\n\n"
        "🐱 با MeowVPN، اینترنت آزاد و امن را تجربه کنید.\n\n"
        "لطفاً یکی از گزینه‌های زیر را انتخاب کنید:",
        reply_markup=get_main_keyboard()
    )

@dp.message(Command("admin"))
async def cmd_admin(message: types.Message):
    """Admin panel"""
    user_data = await get_user_data(message.from_user.id)
    
    if not user_data or user_data.get('role') != 'admin':
        await message.answer("❌ دسترسی ندارید")
        return
    
    await message.answer(
        "👑 پنل ادمین\n\n"
        "لطفاً یکی از گزینه‌ها را انتخاب کنید:",
        reply_markup=get_admin_keyboard()
    )

@dp.message(Command("reseller"))
async def cmd_reseller(message: types.Message):
    """Reseller panel"""
    user_data = await get_user_data(message.from_user.id)
    
    if not user_data or user_data.get('role') != 'reseller':
        await message.answer("❌ دسترسی ندارید")
        return
    
    await message.answer(
        "🏢 پنل نماینده\n\n"
        "لطفاً یکی از گزینه‌ها را انتخاب کنید:",
        reply_markup=get_reseller_keyboard()
    )

# Purchase Flow
@dp.message(F.text == "🛍 خرید سرویس")
async def purchase_service(message: types.Message, state: FSMContext):
    """Start purchase flow"""
    plans = await api_request('GET', 'plans')
    
    if not plans:
        await message.answer("❌ در حال حاضر پلنی موجود نیست")
        return
    
    # Store plans in state
    await state.update_data(plans=plans)
    
    # Create inline keyboard with plans
    buttons = []
    for plan in plans[:10]:  # Limit to 10 plans
        traffic = "نامحدود" if plan['traffic_bytes'] == 0 else f"{plan['traffic_bytes'] // (1024**3)} GB"
        button_text = f"{plan['name']} | {plan['duration_days']} روز | {traffic} | {plan['price_base']:,} ت"
        buttons.append([InlineKeyboardButton(text=button_text, callback_data=f"plan:{plan['id']}")])
    
    buttons.append([InlineKeyboardButton(text="🔙 انصراف", callback_data="cancel_purchase")])
    
    await message.answer(
        "📦 لطفاً پلن مورد نظر خود را انتخاب کنید:\n\n"
        "💡 نکته: قیمت‌ها به تومان است",
        reply_markup=InlineKeyboardMarkup(inline_keyboard=buttons)
    )
    await state.set_state(PurchaseStates.selecting_plan)

@dp.callback_query(F.data.startswith("plan:"), StateFilter(PurchaseStates.selecting_plan))
async def plan_selected(callback: CallbackQuery, state: FSMContext):
    """Handle plan selection"""
    plan_id = int(callback.data.split(":")[1])
    data = await state.get_data()
    plans = data.get('plans', [])
    
    selected_plan = next((p for p in plans if p['id'] == plan_id), None)
    if not selected_plan:
        await callback.answer("❌ پلن یافت نشد")
        return
    
    await state.update_data(selected_plan=selected_plan)
    
    # Get available locations
    locations = await api_request('GET', 'servers/available')
    
    if not locations or not locations.get('locations'):
        await callback.message.edit_text("❌ سروری در دسترس نیست")
        await state.clear()
        return
    
    # Create location keyboard
    buttons = []
    for loc in locations['locations']:
        buttons.append([InlineKeyboardButton(
            text=f"{loc['emoji']} {loc['tag']}",
            callback_data=f"location:{loc['tag']}"
        )])
    
    buttons.append([InlineKeyboardButton(text="🔙 بازگشت", callback_data="back_to_plans")])
    
    await callback.message.edit_text(
        f"📦 پلن انتخابی: {selected_plan['name']}\n"
        f"💰 قیمت: {selected_plan['price_base']:,} تومان\n\n"
        "🌍 لطفاً لوکیشن سرور را انتخاب کنید:",
        reply_markup=InlineKeyboardMarkup(inline_keyboard=buttons)
    )
    await state.set_state(PurchaseStates.selecting_location)
    await callback.answer()

@dp.callback_query(F.data.startswith("location:"), StateFilter(PurchaseStates.selecting_location))
async def location_selected(callback: CallbackQuery, state: FSMContext):
    """Handle location selection"""
    location_tag = callback.data.split(":")[1]
    data = await state.get_data()
    selected_plan = data.get('selected_plan')
    
    await state.update_data(selected_location=location_tag)
    
    # Confirmation
    confirm_keyboard = InlineKeyboardMarkup(inline_keyboard=[
        [
            InlineKeyboardButton(text="✅ تایید و خرید", callback_data="confirm_purchase"),
            InlineKeyboardButton(text="❌ انصراف", callback_data="cancel_purchase")
        ]
    ])
    
    await callback.message.edit_text(
        f"🛒 خلاصه سفارش:\n\n"
        f"📦 پلن: {selected_plan['name']}\n"
        f"⏱ مدت: {selected_plan['duration_days']} روز\n"
        f"🌍 لوکیشن: {location_tag}\n"
        f"💰 قیمت: {selected_plan['price_base']:,} تومان\n\n"
        f"آیا تایید می‌کنید؟",
        reply_markup=confirm_keyboard
    )
    await state.set_state(PurchaseStates.confirming)
    await callback.answer()

@dp.callback_query(F.data == "confirm_purchase", StateFilter(PurchaseStates.confirming))
async def confirm_purchase(callback: CallbackQuery, state: FSMContext):
    """Confirm and create subscription"""
    data = await state.get_data()
    selected_plan = data.get('selected_plan')
    selected_location = data.get('selected_location')
    
    token = await get_user_token(callback.from_user.id)
    if not token:
        await callback.message.edit_text("❌ خطا در احراز هویت. لطفاً دوباره تلاش کنید.")
        await state.clear()
        return
    
    # Create subscription
    result = await api_request(
        'POST', 'subscriptions',
        data={'plan_id': selected_plan['id'], 'location_tag': selected_location},
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if not result:
        await callback.message.edit_text("❌ خطا در ایجاد سرویس. لطفاً موجودی کیف پول خود را بررسی کنید.")
        await state.clear()
        return
    
    await callback.message.edit_text(
        f"✅ سرویس شما با موفقیت ایجاد شد!\n\n"
        f"🆔 شناسه: #{result['id']}\n"
        f"📦 پلن: {selected_plan['name']}\n"
        f"🌍 لوکیشن: {selected_location}\n\n"
        f"برای دریافت لینک اتصال، به بخش «سرویس‌های من» مراجعه کنید."
    )
    await state.clear()
    await callback.answer("✅ سرویس ایجاد شد")

@dp.callback_query(F.data == "cancel_purchase")
async def cancel_purchase(callback: CallbackQuery, state: FSMContext):
    """Cancel purchase"""
    await state.clear()
    await callback.message.edit_text("❌ خرید لغو شد.")
    await callback.answer()

# My Services
@dp.message(F.text == "🔧 سرویس‌های من")
async def my_services(message: types.Message):
    """Show user services"""
    token = await get_user_token(message.from_user.id)
    if not token:
        await message.answer("❌ لطفاً ابتدا /start را بزنید")
        return
    
    subscriptions = await api_request(
        'GET', 'subscriptions',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if not subscriptions:
        await message.answer(
            "📭 شما هیچ سرویس فعالی ندارید.\n\n"
            "برای خرید سرویس، از دکمه «خرید سرویس» استفاده کنید."
        )
        return
    
    # Create buttons for each service
    buttons = []
    for sub in subscriptions[:10]:
        status_emoji = "✅" if sub['status'] == 'active' else "❌"
        server_name = sub.get('server', {}).get('name', 'چند سرور')
        buttons.append([InlineKeyboardButton(
            text=f"{status_emoji} سرویس #{sub['id']} | {server_name}",
            callback_data=f"service:{sub['id']}"
        )])
    
    await message.answer(
        f"🔧 سرویس‌های شما ({len(subscriptions)} عدد):\n\n"
        "برای مشاهده جزئیات و دریافت لینک، روی هر سرویس کلیک کنید:",
        reply_markup=InlineKeyboardMarkup(inline_keyboard=buttons)
    )

@dp.callback_query(F.data.startswith("service:"))
async def show_service_detail(callback: CallbackQuery):
    """Show service details"""
    subscription_id = int(callback.data.split(":")[1])
    
    token = await get_user_token(callback.from_user.id)
    subscription = await api_request(
        'GET', f'subscriptions/{subscription_id}',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if not subscription:
        await callback.answer("❌ سرویس یافت نشد")
        return
    
    # Calculate traffic
    total_traffic = subscription.get('total_traffic', 0)
    used_traffic = subscription.get('used_traffic', 0)
    if total_traffic > 0:
        traffic_text = f"{used_traffic / (1024**3):.2f} / {total_traffic / (1024**3):.2f} GB"
        remaining_percent = ((total_traffic - used_traffic) / total_traffic) * 100
    else:
        traffic_text = "نامحدود"
        remaining_percent = 100
    
    # Calculate remaining days
    expire_date = subscription.get('expire_date')
    if expire_date:
        expire = datetime.fromisoformat(expire_date.replace('Z', '+00:00'))
        remaining_days = (expire - datetime.now(expire.tzinfo)).days
        days_text = f"{remaining_days} روز" if remaining_days > 0 else "منقضی شده"
    else:
        days_text = "نامحدود"
    
    status_emoji = "✅" if subscription['status'] == 'active' else "❌"
    server = subscription.get('server', {})
    
    await callback.message.edit_text(
        f"📦 جزئیات سرویس #{subscription['id']}\n\n"
        f"وضعیت: {status_emoji} {'فعال' if subscription['status'] == 'active' else 'غیرفعال'}\n"
        f"سرور: {server.get('flag_emoji', '🌍')} {server.get('name', 'چند سرور')}\n"
        f"ترافیک: {traffic_text}\n"
        f"اعتبار: {days_text}\n\n"
        f"از دکمه‌های زیر برای مدیریت سرویس استفاده کنید:",
        reply_markup=get_service_keyboard(subscription_id)
    )
    await callback.answer()

@dp.callback_query(F.data.startswith("get_link:"))
async def get_service_link(callback: CallbackQuery):
    """Get subscription link"""
    subscription_id = int(callback.data.split(":")[1])
    
    token = await get_user_token(callback.from_user.id)
    subscription = await api_request(
        'GET', f'subscriptions/{subscription_id}',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if not subscription:
        await callback.answer("❌ سرویس یافت نشد")
        return
    
    sub_link = f"{SUBSCRIPTION_PUBLIC_URL}/api/sub/{subscription['uuid']}"
    
    await callback.message.answer(
        f"🔗 لینک اشتراک سرویس #{subscription_id}:\n\n"
        f"`{sub_link}`\n\n"
        "این لینک را در اپلیکیشن V2Ray خود وارد کنید.",
        parse_mode="Markdown"
    )
    await callback.answer()

@dp.callback_query(F.data.startswith("get_qr:"))
async def get_service_qr(callback: CallbackQuery):
    """Get subscription QR code"""
    subscription_id = int(callback.data.split(":")[1])
    
    token = await get_user_token(callback.from_user.id)
    subscription = await api_request(
        'GET', f'subscriptions/{subscription_id}',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if not subscription:
        await callback.answer("❌ سرویس یافت نشد")
        return
    
    sub_link = f"{SUBSCRIPTION_PUBLIC_URL}/api/sub/{subscription['uuid']}"
    
    # Generate QR code
    qr = qrcode.QRCode(version=1, box_size=10, border=5)
    qr.add_data(sub_link)
    qr.make(fit=True)
    
    img = qr.make_image(fill_color="black", back_color="white")
    bio = BytesIO()
    img.save(bio, 'PNG')
    bio.seek(0)
    
    await callback.message.answer_photo(
        types.BufferedInputFile(bio.read(), filename="qrcode.png"),
        caption=f"📱 QR Code سرویس #{subscription_id}\n\nاین کد را با اپلیکیشن V2Ray اسکن کنید."
    )
    await callback.answer()

@dp.callback_query(F.data == "back_to_services")
async def back_to_services(callback: CallbackQuery):
    """Go back to services list"""
    # Get user's subscriptions and show them
    token = await get_user_token(callback.from_user.id)
    if not token:
        await callback.answer("❌ خطا")
        return
    
    subscriptions = await api_request(
        'GET', 'subscriptions',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if not subscriptions:
        await callback.message.edit_text("📭 شما هیچ سرویس فعالی ندارید.")
        await callback.answer()
        return
    
    # Create buttons for each service
    buttons = []
    for sub in subscriptions[:10]:
        status_emoji = "✅" if sub['status'] == 'active' else "❌"
        server_name = sub.get('server', {}).get('name', 'چند سرور') if sub.get('server') else 'چند سرور'
        buttons.append([InlineKeyboardButton(
            text=f"{status_emoji} سرویس #{sub['id']} | {server_name}",
            callback_data=f"service:{sub['id']}"
        )])
    
    await callback.message.edit_text(
        f"🔧 سرویس‌های شما ({len(subscriptions)} عدد):\n\n"
        "برای مشاهده جزئیات و دریافت لینک، روی هر سرویس کلیک کنید:",
        reply_markup=InlineKeyboardMarkup(inline_keyboard=buttons)
    )
    await callback.answer()


@dp.callback_query(F.data.startswith("renew:"))
async def renew_service(callback: CallbackQuery):
    """Renew subscription"""
    subscription_id = int(callback.data.split(":")[1])
    
    token = await get_user_token(callback.from_user.id)
    
    # Get subscription details
    subscription = await api_request(
        'GET', f'subscriptions/{subscription_id}',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if not subscription:
        await callback.answer("❌ سرویس یافت نشد")
        return
    
    plan = subscription.get('plan', {})
    price = plan.get('price_base', 0)
    
    # Get user balance
    user_data = await get_user_data(callback.from_user.id)
    balance = user_data.get('wallet_balance', 0) if user_data else 0
    
    if balance < price:
        await callback.message.edit_text(
            f"❌ موجودی کافی نیست\n\n"
            f"💰 موجودی شما: {balance:,.0f} تومان\n"
            f"💳 هزینه تمدید: {price:,.0f} تومان\n\n"
            f"لطفاً ابتدا کیف پول خود را شارژ کنید.",
            reply_markup=InlineKeyboardMarkup(inline_keyboard=[
                [InlineKeyboardButton(text="💰 شارژ کیف پول", callback_data="deposit")],
                [InlineKeyboardButton(text="🔙 بازگشت", callback_data=f"service:{subscription_id}")]
            ])
        )
        await callback.answer()
        return
    
    # Confirm renewal
    await callback.message.edit_text(
        f"🔄 تمدید سرویس #{subscription_id}\n\n"
        f"📦 پلن: {plan.get('name', '-')}\n"
        f"💰 هزینه: {price:,.0f} تومان\n"
        f"💳 موجودی فعلی: {balance:,.0f} تومان\n\n"
        f"آیا تایید می‌کنید؟",
        reply_markup=InlineKeyboardMarkup(inline_keyboard=[
            [
                InlineKeyboardButton(text="✅ تایید و تمدید", callback_data=f"confirm_renew:{subscription_id}"),
                InlineKeyboardButton(text="❌ انصراف", callback_data=f"service:{subscription_id}")
            ]
        ])
    )
    await callback.answer()


@dp.callback_query(F.data.startswith("confirm_renew:"))
async def confirm_renew(callback: CallbackQuery):
    """Confirm and process renewal"""
    subscription_id = int(callback.data.split(":")[1])
    
    token = await get_user_token(callback.from_user.id)
    
    result = await api_request(
        'POST', f'subscriptions/{subscription_id}/renew',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if result:
        await callback.message.edit_text(
            f"✅ سرویس #{subscription_id} با موفقیت تمدید شد!\n\n"
            f"برای مشاهده جزئیات، به بخش «سرویس‌های من» مراجعه کنید."
        )
    else:
        await callback.message.edit_text(
            f"❌ خطا در تمدید سرویس\n\n"
            f"لطفاً موجودی کیف پول را بررسی کنید یا با پشتیبانی تماس بگیرید."
        )
    
    await callback.answer()


@dp.callback_query(F.data.startswith("change_location:"))
async def change_location(callback: CallbackQuery, state: FSMContext):
    """Start change location flow"""
    subscription_id = int(callback.data.split(":")[1])
    
    token = await get_user_token(callback.from_user.id)
    
    # Get available locations
    locations = await api_request(
        'GET', 'servers/available',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if not locations or not locations.get('locations'):
        await callback.message.edit_text("❌ سروری در دسترس نیست")
        await callback.answer()
        return
    
    # Store subscription_id in state
    await state.update_data(change_location_sub_id=subscription_id)
    
    # Create location keyboard
    buttons = []
    for loc in locations['locations']:
        buttons.append([InlineKeyboardButton(
            text=f"{loc['emoji']} {loc['tag']}",
            callback_data=f"new_location:{loc['tag']}"
        )])
    
    buttons.append([InlineKeyboardButton(text="🔙 بازگشت", callback_data=f"service:{subscription_id}")])
    
    await callback.message.edit_text(
        f"🌍 تغییر لوکیشن سرویس #{subscription_id}\n\n"
        "لوکیشن جدید را انتخاب کنید:",
        reply_markup=InlineKeyboardMarkup(inline_keyboard=buttons)
    )
    await callback.answer()


@dp.callback_query(F.data.startswith("new_location:"))
async def confirm_new_location(callback: CallbackQuery, state: FSMContext):
    """Confirm and change location"""
    location_tag = callback.data.split(":")[1]
    data = await state.get_data()
    subscription_id = data.get('change_location_sub_id')
    
    if not subscription_id:
        await callback.answer("❌ خطا، لطفاً دوباره تلاش کنید")
        return
    
    token = await get_user_token(callback.from_user.id)
    
    result = await api_request(
        'POST', f'subscriptions/{subscription_id}/change-location',
        data={'location_tag': location_tag},
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if result:
        await callback.message.edit_text(
            f"✅ لوکیشن سرویس #{subscription_id} با موفقیت به {location_tag} تغییر کرد!\n\n"
            f"⚠️ توجه: لینک اتصال شما تغییر کرده است. لطفاً لینک جدید را دریافت کنید."
        )
        await state.clear()
    else:
        await callback.message.edit_text(
            f"❌ خطا در تغییر لوکیشن\n\n"
            f"لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید."
        )
    
    await callback.answer()

# Profile & Wallet
@dp.message(F.text == "👤 پروفایل و کیف پول")
async def show_profile(message: types.Message):
    """Show user profile"""
    user_data = await get_user_data(message.from_user.id)
    
    if not user_data:
        await message.answer("❌ خطا در دریافت اطلاعات. لطفاً /start را بزنید.")
        return
    
    wallet_balance = user_data.get('wallet_balance', 0)
    
    await message.answer(
        f"👤 پروفایل شما\n\n"
        f"🆔 شناسه: #{user_data['id']}\n"
        f"👤 نام کاربری: {user_data.get('username', '-')}\n"
        f"💰 موجودی کیف پول: {wallet_balance:,.0f} تومان\n\n"
        f"از دکمه‌های زیر استفاده کنید:",
        reply_markup=get_profile_keyboard()
    )

@dp.callback_query(F.data == "deposit")
async def start_deposit(callback: CallbackQuery, state: FSMContext):
    """Start deposit flow"""
    await callback.message.edit_text(
        "💰 شارژ کیف پول\n\n"
        "لطفاً مبلغ مورد نظر را به تومان وارد کنید:\n"
        "(حداقل 10,000 تومان)",
    )
    await state.set_state(DepositStates.entering_amount)
    await callback.answer()

@dp.message(StateFilter(DepositStates.entering_amount))
async def deposit_amount_entered(message: types.Message, state: FSMContext):
    """Handle deposit amount"""
    try:
        amount = int(message.text.replace(',', '').replace('،', ''))
        if amount < 10000:
            await message.answer("❌ حداقل مبلغ شارژ 10,000 تومان است.")
            return
    except ValueError:
        await message.answer("❌ لطفاً فقط عدد وارد کنید.")
        return
    
    await state.update_data(deposit_amount=amount)
    
    await message.answer(
        f"💰 مبلغ: {amount:,} تومان\n\n"
        "لطفاً روش پرداخت را انتخاب کنید:",
        reply_markup=get_gateway_keyboard()
    )
    await state.set_state(DepositStates.selecting_gateway)

@dp.callback_query(F.data.startswith("gateway:"), StateFilter(DepositStates.selecting_gateway))
async def gateway_selected(callback: CallbackQuery, state: FSMContext):
    """Handle gateway selection"""
    gateway = callback.data.split(":")[1]
    data = await state.get_data()
    amount = data.get('deposit_amount')
    
    token = await get_user_token(callback.from_user.id)
    
    if gateway == "zibal":
        # Create payment via API
        result = await api_request(
            'POST', 'transactions/deposit',
            data={'amount': amount * 10, 'gateway': 'zibal'},  # Convert to Rials
            headers={'Authorization': f'Bearer {token}'}
        )
        
        if result and result.get('payment_url'):
            await callback.message.edit_text(
                f"💳 پرداخت آنلاین\n\n"
                f"مبلغ: {amount:,} تومان\n\n"
                f"برای پرداخت روی لینک زیر کلیک کنید:\n"
                f"{result['payment_url']}"
            )
        else:
            await callback.message.edit_text("❌ خطا در ایجاد لینک پرداخت. لطفاً دوباره تلاش کنید.")
        
        await state.clear()
    
    elif gateway == "card_to_card":
        # Card number for payment
        card_number = os.getenv('CARD_NUMBER', '6037-XXXX-XXXX-XXXX')
        card_holder = os.getenv('CARD_HOLDER', 'نام صاحب کارت')
        
        await callback.message.edit_text(
            f"🏦 کارت به کارت\n\n"
            f"مبلغ: {amount:,} تومان\n\n"
            f"لطفاً مبلغ را به کارت زیر واریز کنید:\n\n"
            f"💳 شماره کارت: `{card_number}`\n"
            f"👤 به نام: {card_holder}\n\n"
            f"پس از واریز، تصویر رسید را ارسال کنید:",
            parse_mode="Markdown"
        )
        await state.update_data(gateway='card_to_card')
        await state.set_state(DepositStates.uploading_proof)
    
    await callback.answer()

@dp.message(StateFilter(DepositStates.uploading_proof), F.photo)
async def proof_uploaded(message: types.Message, state: FSMContext):
    """Handle proof image upload: download photo and send to API as multipart."""
    data = await state.get_data()
    amount = data.get('deposit_amount')
    
    token = await get_user_token(message.from_user.id)
    if not token:
        await message.answer("❌ خطا در احراز هویت. لطفاً /start را بزنید.")
        await state.clear()
        return

    # Download largest photo (last in list)
    try:
        file = await bot.get_file(message.photo[-1].file_id)
        bio = await bot.download_file(file)
        proof_bytes = bio.read() if hasattr(bio, 'read') else bio
    except Exception as e:
        logger.warning(f"Failed to download proof image: {e}")
        await message.answer("❌ خطا در دریافت تصویر. لطفاً دوباره ارسال کنید.")
        return

    result = await api_request_deposit_with_proof(
        amount_rials=amount * 10,
        proof_image_bytes=proof_bytes,
        token=token,
    )
    
    if result:
        await message.answer(
            f"✅ درخواست شارژ ثبت شد!\n\n"
            f"مبلغ: {amount:,} تومان\n"
            f"شناسه: #{result.get('transaction', {}).get('id', '-')}\n\n"
            f"پس از بررسی توسط ادمین، موجودی شما شارژ خواهد شد."
        )
    else:
        await message.answer("❌ خطا در ثبت درخواست. لطفاً دوباره تلاش کنید.")
    
    await state.clear()

@dp.callback_query(F.data == "cancel_deposit")
async def cancel_deposit(callback: CallbackQuery, state: FSMContext):
    """Cancel deposit"""
    await state.clear()
    await callback.message.edit_text("❌ شارژ لغو شد.")
    await callback.answer()

@dp.callback_query(F.data == "transaction_history")
async def show_transaction_history(callback: CallbackQuery):
    """Show transaction history"""
    token = await get_user_token(callback.from_user.id)
    
    transactions = await api_request(
        'GET', 'transactions',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if not transactions or not transactions.get('data'):
        await callback.message.edit_text("📜 تاریخچه تراکنش خالی است.")
        await callback.answer()
        return
    
    text = "📜 آخرین تراکنش‌های شما:\n\n"
    for tx in transactions['data'][:10]:
        status_emoji = "✅" if tx['status'] == 'completed' else "⏳" if tx['status'] == 'pending' else "❌"
        tx_type = "شارژ" if tx['type'] == 'deposit' else "خرید" if tx['type'] == 'purchase' else tx['type']
        text += f"{status_emoji} {tx_type} | {tx['amount']:,.0f} ﷼\n"
    
    await callback.message.edit_text(text)
    await callback.answer()

@dp.callback_query(F.data == "referral_link")
async def show_referral_link(callback: CallbackQuery):
    """Show referral link"""
    user_data = await get_user_data(callback.from_user.id)
    if not user_data:
        await callback.answer("❌ خطا")
        return
    
    bot_username = (await bot.get_me()).username
    referral_link = f"https://t.me/{bot_username}?start={user_data['id']}"
    
    await callback.message.edit_text(
        f"🔗 لینک دعوت شما:\n\n"
        f"`{referral_link}`\n\n"
        f"با دعوت دوستان خود، از هر خرید آن‌ها کمیسیون دریافت کنید!",
        parse_mode="Markdown"
    )
    await callback.answer()

# Tutorial
@dp.message(F.text == "🍏 آموزش اتصال")
async def show_tutorial(message: types.Message):
    """Show connection tutorial"""
    tutorial_keyboard = InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text="📱 آموزش iOS", callback_data="tutorial:ios")],
        [InlineKeyboardButton(text="🤖 آموزش Android", callback_data="tutorial:android")],
        [InlineKeyboardButton(text="💻 آموزش Windows", callback_data="tutorial:windows")],
        [InlineKeyboardButton(text="🍎 آموزش macOS", callback_data="tutorial:macos")],
    ])
    
    await message.answer(
        "🍏 آموزش اتصال\n\n"
        "لطفاً سیستم‌عامل خود را انتخاب کنید:",
        reply_markup=tutorial_keyboard
    )

@dp.callback_query(F.data.startswith("tutorial:"))
async def show_tutorial_detail(callback: CallbackQuery):
    """Show tutorial for specific platform"""
    platform = callback.data.split(":")[1]
    
    tutorials = {
        "ios": "📱 آموزش iOS:\n\n1. اپلیکیشن Streisand را از App Store دانلود کنید\n2. وارد برنامه شوید\n3. روی + کلیک کنید\n4. لینک اشتراک را Paste کنید\n5. روی دکمه اتصال کلیک کنید",
        "android": "🤖 آموزش Android:\n\n1. اپلیکیشن v2rayNG را نصب کنید\n2. وارد برنامه شوید\n3. روی + کلیک کنید\n4. گزینه 'Import config from clipboard' را بزنید\n5. لینک اشتراک را Paste کنید\n6. روی دکمه V کلیک کنید",
        "windows": "💻 آموزش Windows:\n\n1. اپلیکیشن Nekoray یا v2rayN را دانلود کنید\n2. برنامه را اجرا کنید\n3. از منو گزینه افزودن سرور را بزنید\n4. لینک اشتراک را Paste کنید\n5. روی Connect کلیک کنید",
        "macos": "🍎 آموزش macOS:\n\n1. اپلیکیشن V2Box را از App Store دانلود کنید\n2. وارد برنامه شوید\n3. لینک اشتراک را اضافه کنید\n4. روی دکمه اتصال کلیک کنید",
    }
    
    await callback.message.edit_text(tutorials.get(platform, "آموزش موجود نیست"))
    await callback.answer()

# Support
@dp.message(F.text == "📞 پشتیبانی")
async def show_support(message: types.Message):
    """Show support info"""
    support_username = os.getenv('SUPPORT_USERNAME', '@support')
    
    await message.answer(
        f"📞 پشتیبانی MeowVPN\n\n"
        f"برای ارتباط با پشتیبانی:\n"
        f"👤 {support_username}\n\n"
        f"ساعات پاسخگویی: 9 صبح تا 12 شب"
    )

# Free Test
@dp.message(F.text == "🧪 تست رایگان")
async def free_test(message: types.Message):
    """Show free test info"""
    await message.answer(
        "🧪 تست رایگان\n\n"
        "برای دریافت سرویس تست رایگان:\n"
        "1. یک دوست را به ربات دعوت کنید\n"
        "2. پس از عضویت دوست شما، سرویس تست فعال می‌شود\n\n"
        "یا با پشتیبانی تماس بگیرید."
    )

# Admin: Quick Stats
@dp.message(F.text == "📊 آمار سریع")
async def admin_quick_stats(message: types.Message):
    """Show quick stats for admin"""
    user_data = await get_user_data(message.from_user.id)
    if not user_data or user_data.get('role') != 'admin':
        return
    
    token = await get_user_token(message.from_user.id)
    stats = await api_request(
        'GET', 'dashboard/stats',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if not stats:
        await message.answer("❌ خطا در دریافت آمار")
        return
    
    await message.answer(
        f"📊 آمار سریع:\n\n"
        f"👥 کل کاربران: {stats.get('total_users', 0):,}\n"
        f"✅ سرویس‌های فعال: {stats.get('active_subscriptions', 0):,}\n"
        f"💰 فروش امروز: {stats.get('today_sales', 0):,} ﷼\n"
        f"📈 فروش ماهانه: {stats.get('monthly_sales', 0):,} ﷼"
    )

# Admin: Approve Transaction
@dp.message(F.text == "💳 تایید تراکنش")
async def admin_pending_transactions(message: types.Message):
    """Show pending transactions for admin"""
    user_data = await get_user_data(message.from_user.id)
    if not user_data or user_data.get('role') != 'admin':
        return
    
    token = await get_user_token(message.from_user.id)
    transactions = await api_request(
        'GET', 'transactions/pending',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if not transactions or not transactions.get('data'):
        await message.answer("✅ هیچ تراکنش در انتظار تاییدی وجود ندارد.")
        return
    
    buttons = []
    for tx in transactions['data'][:10]:
        user = tx.get('user', {})
        buttons.append([InlineKeyboardButton(
            text=f"#{tx['id']} | {user.get('username', '-')} | {tx['amount']:,.0f} ﷼",
            callback_data=f"admin_tx:{tx['id']}"
        )])
    
    await message.answer(
        "💳 تراکنش‌های در انتظار تایید:\n\n"
        "برای تایید یا رد، روی هر تراکنش کلیک کنید:",
        reply_markup=InlineKeyboardMarkup(inline_keyboard=buttons)
    )

@dp.callback_query(F.data.startswith("admin_tx:"))
async def admin_transaction_detail(callback: CallbackQuery):
    """Show transaction detail for admin"""
    tx_id = int(callback.data.split(":")[1])
    
    keyboard = InlineKeyboardMarkup(inline_keyboard=[
        [
            InlineKeyboardButton(text="✅ تایید", callback_data=f"approve_tx:{tx_id}"),
            InlineKeyboardButton(text="❌ رد", callback_data=f"reject_tx:{tx_id}")
        ],
        [InlineKeyboardButton(text="🔙 بازگشت", callback_data="back_to_pending")]
    ])
    
    await callback.message.edit_text(
        f"💳 تراکنش #{tx_id}\n\n"
        "عملیات مورد نظر را انتخاب کنید:",
        reply_markup=keyboard
    )
    await callback.answer()

@dp.callback_query(F.data.startswith("approve_tx:"))
async def approve_transaction(callback: CallbackQuery):
    """Approve transaction"""
    tx_id = int(callback.data.split(":")[1])
    token = await get_user_token(callback.from_user.id)
    
    result = await api_request(
        'POST', f'transactions/{tx_id}/approve',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if result:
        await callback.message.edit_text(f"✅ تراکنش #{tx_id} تایید شد.")
    else:
        await callback.message.edit_text(f"❌ خطا در تایید تراکنش #{tx_id}")
    
    await callback.answer()

@dp.callback_query(F.data.startswith("reject_tx:"))
async def reject_transaction(callback: CallbackQuery):
    """Reject transaction"""
    tx_id = int(callback.data.split(":")[1])
    token = await get_user_token(callback.from_user.id)
    
    result = await api_request(
        'POST', f'transactions/{tx_id}/reject',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if result:
        await callback.message.edit_text(f"❌ تراکنش #{tx_id} رد شد.")
    else:
        await callback.message.edit_text(f"❌ خطا در رد تراکنش #{tx_id}")
    
    await callback.answer()

# Admin: Broadcast
@dp.message(F.text == "📢 ارسال همگانی")
async def admin_broadcast_start(message: types.Message, state: FSMContext):
    """Start broadcast"""
    user_data = await get_user_data(message.from_user.id)
    if not user_data or user_data.get('role') != 'admin':
        return
    
    await message.answer(
        "📢 ارسال همگانی\n\n"
        "پیام خود را برای ارسال به همه کاربران بنویسید:\n\n"
        "برای لغو: /cancel"
    )
    await state.set_state(AdminStates.broadcasting)

@dp.message(StateFilter(AdminStates.broadcasting))
async def admin_broadcast_send(message: types.Message, state: FSMContext):
    """Send broadcast"""
    if message.text == "/cancel":
        await state.clear()
        await message.answer("❌ ارسال همگانی لغو شد.", reply_markup=get_admin_keyboard())
        return
    
    token = await get_user_token(message.from_user.id)
    
    # Fetch all users with telegram_id via pagination (backend caps per_page at 100)
    user_list = []
    page = 1
    while True:
        resp = await api_request(
            'GET', f'users?has_telegram=1&per_page=100&page={page}',
            headers={'Authorization': f'Bearer {token}'}
        )
        if not resp or not resp.get('data'):
            break
        user_list.extend(resp['data'])
        last_page = resp.get('last_page', 1)
        if page >= last_page:
            break
        page += 1
    
    if not user_list:
        await message.answer("❌ کاربری برای ارسال پیام یافت نشد.")
        await state.clear()
        return
    total_users = len(user_list)
    sent_count = 0
    failed_count = 0
    
    progress_msg = await message.answer(f"📤 در حال ارسال به {total_users} کاربر...")
    
    for user in user_list:
        telegram_id = user.get('telegram_id')
        if telegram_id:
            try:
                await bot.send_message(telegram_id, message.text)
                sent_count += 1
                await asyncio.sleep(0.05)  # Rate limiting
            except Exception as e:
                failed_count += 1
                logger.warning(f"Failed to send to {telegram_id}: {e}")
        
        # Update progress every 50 users
        if (sent_count + failed_count) % 50 == 0:
            try:
                await progress_msg.edit_text(
                    f"📤 در حال ارسال...\n"
                    f"✅ ارسال شده: {sent_count}\n"
                    f"❌ ناموفق: {failed_count}\n"
                    f"📊 پیشرفت: {sent_count + failed_count}/{total_users}"
                )
            except:
                pass
    
    await progress_msg.edit_text(
        f"✅ ارسال همگانی تمام شد!\n\n"
        f"📊 نتایج:\n"
        f"✅ موفق: {sent_count}\n"
        f"❌ ناموفق: {failed_count}\n"
        f"📨 کل: {total_users}"
    )
    await state.clear()

# Reseller: Sub-users Stats
@dp.message(F.text == "📊 آمار زیرمجموعه")
async def reseller_sub_stats(message: types.Message):
    """Show sub-users stats for reseller"""
    user_data = await get_user_data(message.from_user.id)
    if not user_data or user_data.get('role') != 'reseller':
        return
    
    token = await get_user_token(message.from_user.id)
    
    # Get reseller's sub-users
    reseller_id = user_data.get('id')
    users_response = await api_request(
        'GET', f'resellers/{reseller_id}/users',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if not users_response or not users_response.get('data'):
        await message.answer("📊 آمار زیرمجموعه\n\n❌ هیچ کاربری در زیرمجموعه شما وجود ندارد.")
        return
    
    users = users_response.get('data', [])
    total_users = len(users)
    
    # Get subscriptions count (API returns array or { data: [] })
    subscriptions_response = await api_request(
        'GET', 'subscriptions',
        headers={'Authorization': f'Bearer {token}'}
    )
    subs_list = _subscriptions_list(subscriptions_response)
    sub_user_ids = [u['id'] for u in users]
    active_subscriptions = sum(
        1 for sub in subs_list
        if sub.get('user_id') in sub_user_ids and sub.get('status') == 'active'
    )
    
    await message.answer(
        f"📊 آمار زیرمجموعه\n\n"
        f"👥 تعداد کاربران: {total_users}\n"
        f"✅ سرویس‌های فعال: {active_subscriptions}\n"
        f"💰 موجودی کیف پول: {user_data.get('wallet_balance', 0):,.0f} تومان"
    )

# Reseller: Sub-users List
@dp.message(F.text == "👥 کاربران من")
async def reseller_sub_users(message: types.Message):
    """Show sub-users list for reseller"""
    user_data = await get_user_data(message.from_user.id)
    if not user_data or user_data.get('role') != 'reseller':
        return
    
    token = await get_user_token(message.from_user.id)
    reseller_id = user_data.get('id')
    
    users_response = await api_request(
        'GET', f'resellers/{reseller_id}/users',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if not users_response or not users_response.get('data'):
        await message.answer("👥 کاربران من\n\n❌ هیچ کاربری در زیرمجموعه شما وجود ندارد.")
        return
    
    users = users_response.get('data', [])
    text = "👥 کاربران زیرمجموعه شما:\n\n"
    
    for i, user in enumerate(users[:20], 1):  # Limit to 20 users
        username = user.get('username', '-')
        user_id = user.get('id')
        text += f"{i}. {username} (ID: {user_id})\n"
    
    if len(users) > 20:
        text += f"\n... و {len(users) - 20} کاربر دیگر"
    
    await message.answer(text)

# Reseller: Sub-users Subscriptions
@dp.message(F.text == "🛒 سرویس‌های زیرمجموعه")
async def reseller_sub_subscriptions(message: types.Message):
    """Show sub-users subscriptions for reseller"""
    user_data = await get_user_data(message.from_user.id)
    if not user_data or user_data.get('role') != 'reseller':
        return
    
    token = await get_user_token(message.from_user.id)
    reseller_id = user_data.get('id')
    
    # Get sub-users
    users_response = await api_request(
        'GET', f'resellers/{reseller_id}/users',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if not users_response or not users_response.get('data'):
        await message.answer("🛒 سرویس‌های زیرمجموعه\n\n❌ هیچ کاربری در زیرمجموعه شما وجود ندارد.")
        return
    
    sub_user_ids = [u['id'] for u in users_response.get('data', [])]
    
    # Get all subscriptions (API returns array or { data: [] })
    subscriptions_response = await api_request(
        'GET', 'subscriptions',
        headers={'Authorization': f'Bearer {token}'}
    )
    subs_list = _subscriptions_list(subscriptions_response)
    sub_subscriptions = [sub for sub in subs_list if sub.get('user_id') in sub_user_ids]
    
    if not sub_subscriptions:
        await message.answer("🛒 سرویس‌های زیرمجموعه\n\n❌ هیچ سرویسی در زیرمجموعه شما وجود ندارد.")
        return
    
    text = "🛒 سرویس‌های زیرمجموعه:\n\n"
    
    for i, sub in enumerate(sub_subscriptions[:10], 1):  # Limit to 10
        user_id = sub.get('user_id')
        status = sub.get('status', 'unknown')
        status_emoji = "✅" if status == 'active' else "⏸" if status == 'paused' else "❌"
        text += f"{i}. سرویس #{sub.get('id')} - کاربر #{user_id} - {status_emoji} {status}\n"
    
    if len(sub_subscriptions) > 10:
        text += f"\n... و {len(sub_subscriptions) - 10} سرویس دیگر"
    
    await message.answer(text)

# Reseller: Sub-users Transactions
@dp.message(F.text == "💳 تراکنش‌های زیرمجموعه")
async def reseller_sub_transactions(message: types.Message):
    """Show sub-users transactions for reseller"""
    user_data = await get_user_data(message.from_user.id)
    if not user_data or user_data.get('role') != 'reseller':
        return
    
    token = await get_user_token(message.from_user.id)
    reseller_id = user_data.get('id')
    
    # Get sub-users
    users_response = await api_request(
        'GET', f'resellers/{reseller_id}/users',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if not users_response or not users_response.get('data'):
        await message.answer("💳 تراکنش‌های زیرمجموعه\n\n❌ هیچ کاربری در زیرمجموعه شما وجود ندارد.")
        return
    
    sub_user_ids = [u['id'] for u in users_response.get('data', [])]
    
    # Get all transactions
    transactions_response = await api_request(
        'GET', 'transactions',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if not transactions_response or not transactions_response.get('data'):
        await message.answer("💳 تراکنش‌های زیرمجموعه\n\n❌ هیچ تراکنشی یافت نشد.")
        return
    
    # Filter transactions of sub-users
    sub_transactions = [
        tx for tx in transactions_response.get('data', [])
        if tx.get('user_id') in sub_user_ids
    ]
    
    if not sub_transactions:
        await message.answer("💳 تراکنش‌های زیرمجموعه\n\n❌ هیچ تراکنشی در زیرمجموعه شما وجود ندارد.")
        return
    
    text = "💳 آخرین تراکنش‌های زیرمجموعه:\n\n"
    
    for i, tx in enumerate(sub_transactions[:10], 1):  # Limit to 10
        status_emoji = "✅" if tx.get('status') == 'completed' else "⏳" if tx.get('status') == 'pending' else "❌"
        tx_type = tx.get('type', 'unknown')
        amount = tx.get('amount', 0) / 10  # Convert from Rials to Tomans
        text += f"{i}. {status_emoji} {tx_type} | {amount:,.0f} تومان | کاربر #{tx.get('user_id')}\n"
    
    if len(sub_transactions) > 10:
        text += f"\n... و {len(sub_transactions) - 10} تراکنش دیگر"
    
    await message.answer(text)

# Reseller: Affiliate Link
@dp.message(F.text == "🔗 لینک بازاریابی")
async def reseller_affiliate_link(message: types.Message):
    """Show affiliate link for reseller"""
    user_data = await get_user_data(message.from_user.id)
    if not user_data or user_data.get('role') != 'reseller':
        return
    
    token = await get_user_token(message.from_user.id)
    
    # Get affiliate link
    affiliate_response = await api_request(
        'GET', 'affiliates/link',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    if not affiliate_response:
        await message.answer("❌ خطا در دریافت لینک بازاریابی")
        return
    
    bot_username = (await bot.get_me()).username
    referral_link = f"https://t.me/{bot_username}?start={user_data.get('id')}"
    
    # Get affiliate stats
    stats_response = await api_request(
        'GET', 'affiliates/stats',
        headers={'Authorization': f'Bearer {token}'}
    )
    
    stats_text = ""
    if stats_response:
        total_earnings = stats_response.get('total_earnings', 0) / 10  # Convert to Tomans
        pending_earnings = stats_response.get('pending_earnings', 0) / 10
        referrals_count = stats_response.get('referrals_count', 0)
        
        stats_text = (
            f"\n\n📊 آمار بازاریابی:\n"
            f"👥 تعداد دعوت شده: {referrals_count}\n"
            f"💰 کل درآمد: {total_earnings:,.0f} تومان\n"
            f"⏳ در انتظار: {pending_earnings:,.0f} تومان"
        )
    
    await message.answer(
        f"🔗 لینک بازاریابی شما:\n\n"
        f"`{referral_link}`\n\n"
        f"با دعوت کاربران، از هر خرید آن‌ها کمیسیون دریافت کنید!{stats_text}",
        parse_mode="Markdown"
    )

# Back to main menu
@dp.message(F.text == "🔙 بازگشت")
async def back_to_main(message: types.Message, state: FSMContext):
    """Back to main menu"""
    await state.clear()
    user_data = await get_user_data(message.from_user.id)
    
    if user_data:
        if user_data.get('role') == 'admin':
            await message.answer("منوی اصلی:", reply_markup=get_main_keyboard())
        elif user_data.get('role') == 'reseller':
            await message.answer("منوی اصلی:", reply_markup=get_main_keyboard())
        else:
            await message.answer("منوی اصلی:", reply_markup=get_main_keyboard())
    else:
        await message.answer("منوی اصلی:", reply_markup=get_main_keyboard())

async def main():
    """Main function"""
    if not BOT_TOKEN or not str(BOT_TOKEN).strip():
        logger.error("TELEGRAM_BOT_TOKEN is not set or empty. Cannot start bot.")
        raise SystemExit(1)
    logger.info("Starting MeowVPN Telegram Bot...")
    await dp.start_polling(bot)

if __name__ == '__main__':
    asyncio.run(main())
