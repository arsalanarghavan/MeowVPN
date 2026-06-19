<!DOCTYPE html>
<html lang="fa-IR" dir="rtl">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>پنل مدیریت وب</title>
<link rel="stylesheet" href="{{ asset('portal/portal.css') }}?v={{ $assetVersion }}"/>
</head>
<body>

		<div class="svp-admin{{ $isReseller ? ' svp-admin--reseller' : '' }}" data-uid="{{ $admin->id }}" data-nonce="{{ $nonce }}" data-ajax="{{ $apiUrl }}" data-is-reseller="{{ $isReseller ? '1' : '0' }}" data-portal-lang="fa">
			<h1 class="svp-admin__title">پنل مدیریت وب</h1>
			<p class="svp-admin__hint">دسترسی با لینک امضاشده از ربات. IPN خودکار کریپتو: <code class="svp-admin__code">{{ $ipnUrl }}</code></p>
			<section class="svp-admin__card">
				<h2>آمار و پنل‌ها</h2>
				<p class="svp-admin__hint">روز نمایش برای «حداکثر آنلاین» هر پنل؛ بقیهٔ شمارش‌ها لحظه‌ای است.</p>
				<div class="svp-admin__daynav" role="group" aria-label="روز آمار">
					@for ($d = 0; $d <= 7; $d++)
						<button type="button" class="svp-btn svp-btn--small{{ $d === 0 ? ' is-active' : '' }}" data-svp-admin-op="stats" data-svp-stats-day="{{ $d }}">{{ $d === 0 ? 'امروز' : '-'.$d }}</button>
					@endfor
				</div>
				<pre class="svp-admin__out" id="svp-adm-stats"></pre>
				<table class="svp-admin__table" id="svp-adm-stats-table" hidden>
					<thead><tr><th>پنل</th><th>Xray فعال</th><th>Xray منقضی</th><th>حداکثر آنلاین (روز انتخاب‌شده)</th></tr></thead>
					<tbody id="svp-adm-stats-tbody"></tbody>
				</table>
			</section>
			<section class="svp-admin__card">
				<h2>صف ثبت‌نام</h2>
				<p class="svp-admin__hint">۵ نفر در هر صفحه (جدیدترین اول). تأیید و رد فقط برای وضعیت «در انتظار».</p>
				<div id="svp-mem-root" class="svp-mem" data-tab="pending" data-offset="0">
					<div class="svp-admin__daynav" role="tablist">
						<button type="button" class="svp-btn svp-btn--small is-active" data-svp-mem-tab="pending">در انتظار</button>
						<button type="button" class="svp-btn svp-btn--small" data-svp-mem-tab="approved">تأییدشده</button>
						<button type="button" class="svp-btn svp-btn--small" data-svp-mem-tab="rejected">رد شده</button>
					</div>
					<p class="svp-admin__hint"><button type="button" class="svp-btn" data-svp-mem-refresh>بارگذاری / تازه‌سازی لیست</button>
						<button type="button" class="svp-btn" data-svp-mem-prev disabled>صفحه قبل</button>
						<button type="button" class="svp-btn" data-svp-mem-next disabled>صفحه بعد</button></p>
					<table class="svp-admin__table">
						<thead><tr><th>شناسه</th><th>کاربر</th><th>وضعیت</th><th>ثبت</th><th>عملیات</th></tr></thead>
						<tbody id="svp-mem-tbody"></tbody>
					</table>
					<div class="svp-mem-detail" id="svp-mem-detail-wrap" hidden>
						<h3>جزئیات</h3>
						<div id="svp-mem-detail-img"></div>
						<pre class="svp-admin__out" id="svp-mem-detail"></pre>
					</div>
				</div>
			</section>
			<section class="svp-admin__card">
				<h2>ساخت سرویس برای کاربر</h2>
				<label>شناسه کاربر (svp_users.id)<input type="number" id="svp-cr-uid" min="1" class="svp-admin__input"/></label>
				<label>شناسه پلن<input type="number" id="svp-cr-pid" min="1" class="svp-admin__input"/></label>
				<label>حجم (گیگ، برای پلن per-GB)<input type="number" id="svp-cr-gb" min="0" class="svp-admin__input" placeholder="0"/></label>
				<label>حالت
					<select id="svp-cr-mode" class="svp-admin__input">
						<option value="free" data-svp-portal-site-only>بدون پرداخت</option>
						<option value="wallet">کسر از کیف پول کاربر</option>
						<option value="invoice">فاکتور به کاربر</option>
					</select>
				</label>
				<button type="button" class="svp-btn svp-btn--primary" data-svp-admin-op="create_service">اجرای ساخت سرویس</button>
				<pre class="svp-admin__out" id="svp-adm-create"></pre>
			</section>
			<section class="svp-admin__card">
				<h2>تمدید سرویس</h2>
				<label>شناسه سرویس<input type="number" id="svp-rn-sid" min="1" class="svp-admin__input"/></label>
				<label>حالت
					<select id="svp-rn-mode" class="svp-admin__input">
						<option value="free" data-svp-portal-site-only>بدون پرداخت</option>
						<option value="wallet">کسر از کیف پول کاربر</option>
						<option value="invoice">فاکتور به کاربر</option>
					</select>
				</label>
				<button type="button" class="svp-btn svp-btn--primary" data-svp-admin-op="renew_service">تمدید</button>
				<pre class="svp-admin__out" id="svp-adm-renew"></pre>
			</section>
			<section class="svp-admin__card">
				<h2>افزایش حجم</h2>
				<label>شناسه سرویس<input type="number" id="svp-v-sid" min="1" class="svp-admin__input"/></label>
				<label>گیگ اضافه<input type="number" id="svp-v-gb" min="1" value="1" class="svp-admin__input"/></label>
				<label>حالت
					<select id="svp-v-mode" class="svp-admin__input">
						<option value="free" data-svp-portal-site-only>بدون پرداخت</option>
						<option value="wallet">کسر از کیف پول کاربر</option>
						<option value="invoice">فاکتور به کاربر</option>
					</select>
				</label>
				<button type="button" class="svp-btn svp-btn--primary" data-svp-admin-op="add_volume">اعمال</button>
				<pre class="svp-admin__out" id="svp-adm-vol"></pre>
			</section>
			<section class="svp-admin__card" data-svp-portal-site-only>
				<h2>عملیات گروهی (فقط Xray)</h2>
				<p class="svp-admin__warn">حداکثر ۲۰۰ سرویس در هر اجرا؛ بار روی پنل ۳x-ui.</p>
				<label>افزودن روز به همه<input type="number" id="svp-bulk-d" min="1" value="1" class="svp-admin__input"/></label>
				<label class="svp-admin__check"><input type="checkbox" id="svp-bulk-ack"/> تأیید می‌کنم که می‌خواهم این عملیات گروهی اجرا شود.</label>
				<button type="button" class="svp-btn" data-svp-admin-op="bulk_days">اجرای افزودن روز</button>
				<label>افزودن گیگ به همه<input type="number" id="svp-bulk-g" min="1" value="1" class="svp-admin__input"/></label>
				<button type="button" class="svp-btn" data-svp-admin-op="bulk_gb">اجرای افزودن حجم</button>
				<pre class="svp-admin__out" id="svp-adm-bulk"></pre>
			</section>
			<section class="svp-admin__card" data-svp-portal-site-only>
				<h2>ریفرال و لینک ربات</h2>
				<p class="svp-admin__hint">برای ویرایش کامل کدهای تخفیف از وردپرس » SimpleVPBot » تب «کدهای تخفیف» استفاده کنید.</p>
				<button type="button" class="svp-btn" data-svp-admin-op="referral_load">بارگذاری تنظیمات</button>
				<label class="svp-admin__check"><input type="checkbox" id="svp-ref-en"/> فعال بودن دعوت</label>
				<label>درصد پورسانت<input type="text" id="svp-ref-pct" class="svp-admin__input" placeholder="10"/></label>
				<label>حداقل مبلغ سفارش (تومان)<input type="text" id="svp-ref-min" class="svp-admin__input" placeholder="0"/></label>
				<label>مبلغ نمونهٔ «کمترین خرید» در متن ربات<input type="text" id="svp-ref-ex-base" class="svp-admin__input" placeholder="170000"/></label>
				<label>تعداد نفر در مثال ربات<input type="number" id="svp-ref-ex-n" min="1" class="svp-admin__input" placeholder="10"/></label>
				<label class="svp-admin__check"><input type="checkbox" id="svp-ref-req"/> دعوت‌کننده باید تأییدشده باشد</label>
				<label>نام کاربری ربات تلگرام (بدون @)<input type="text" id="svp-ref-tg" class="svp-admin__input"/></label>
				<label>نام کاربری ربات بله (بدون @)<input type="text" id="svp-ref-bl" class="svp-admin__input"/></label>
				<button type="button" class="svp-btn svp-btn--primary" data-svp-admin-op="referral_save">ذخیرهٔ ریفرال</button>
				<pre class="svp-admin__out" id="svp-adm-ref"></pre>
			</section>
			<section class="svp-admin__card">
				<h2 data-svp-i18n="transferTitle">انتقال سرویس</h2>
				<p class="svp-admin__hint" data-svp-i18n="transferHint">انتقال مالکیت سرویس به کاربر دیگر (شناسه عددی یا نام کاربری).</p>
				<label data-svp-i18n="transferServiceId">شناسه سرویس<input type="number" id="svp-xfer-sid" min="1" class="svp-admin__input"/></label>
				<label data-svp-i18n="transferTarget">کاربر مقصد<input type="text" id="svp-xfer-tgt" class="svp-admin__input" placeholder="123 یا @username"/></label>
				<button type="button" class="svp-btn svp-btn--primary" data-svp-admin-op="service_transfer" data-svp-i18n="transferSubmit">انتقال</button>
				<pre class="svp-admin__out" id="svp-adm-xfer"></pre>
			</section>
			<section class="svp-admin__card">
				<h2 data-svp-i18n="receiptsTitle">رسیدها</h2>
				<p class="svp-admin__hint" data-svp-i18n="receiptsHint">۱۰ رسید در هر صفحه (جدیدترین اول).</p>
				<div id="svp-rcpt-root" class="svp-rcpt" data-offset="0">
					<p class="svp-admin__hint">
						<button type="button" class="svp-btn" data-svp-rcpt-refresh data-svp-i18n="receiptsRefresh">بارگذاری / تازه‌سازی</button>
						<button type="button" class="svp-btn" data-svp-rcpt-prev disabled data-svp-i18n="receiptsPrev">صفحه قبل</button>
						<button type="button" class="svp-btn" data-svp-rcpt-next disabled data-svp-i18n="receiptsNext">صفحه بعد</button>
					</p>
					<table class="svp-admin__table">
						<thead><tr><th data-svp-i18n="receiptsColId">شناسه</th><th data-svp-i18n="receiptsColUser">کاربر</th><th data-svp-i18n="receiptsColAmount">مبلغ</th><th data-svp-i18n="receiptsColStatus">وضعیت</th><th data-svp-i18n="receiptsColDate">تاریخ</th></tr></thead>
						<tbody id="svp-rcpt-tbody"></tbody>
					</table>
				</div>
			</section>
			<section class="svp-admin__card">
				<h2 data-svp-i18n="discountTitle">کدهای تخفیف</h2>
				<p class="svp-admin__hint" data-svp-i18n="discountHint">فهرست، ایجاد/ویرایش و حذف کدهای تخفیف در محدودهٔ حساب شما.</p>
				<button type="button" class="svp-btn" data-svp-admin-op="discount_list" data-svp-i18n="discountLoadList">بارگذاری لیست</button>
				<label data-svp-i18n="discountIdEdit">شناسه (۰ = جدید)<input type="number" id="svp-disc-id" min="0" class="svp-admin__input" placeholder="0"/></label>
				<label data-svp-i18n="discountCode">کد<input type="text" id="svp-disc-code" class="svp-admin__input" placeholder="SAVE10"/></label>
				<label data-svp-i18n="discountType">نوع
					<select id="svp-disc-type" class="svp-admin__input">
						<option value="percent">percent</option>
						<option value="fixed_toman">fixed_toman</option>
						<option value="percent_per_gb">percent_per_gb</option>
						<option value="fixed_per_gb">fixed_per_gb</option>
					</select>
				</label>
				<label data-svp-i18n="discountValue">مقدار<input type="text" id="svp-disc-value" class="svp-admin__input" placeholder="10"/></label>
				<label data-svp-i18n="discountMaxUses">حداکثر استفاده (خالی = نامحدود)<input type="text" id="svp-disc-max" class="svp-admin__input"/></label>
				<label data-svp-i18n="discountValidFrom">معتبر از (YYYY-MM-DD HH:MM، اختیاری)<input type="text" id="svp-disc-from" class="svp-admin__input" placeholder="2026-01-01 00:00"/></label>
				<label data-svp-i18n="discountValidUntil">معتبر تا (YYYY-MM-DD HH:MM، اختیاری)<input type="text" id="svp-disc-until" class="svp-admin__input"/></label>
				<label data-svp-i18n="discountMinOrder">حداقل سفارش (تومان، اختیاری)<input type="text" id="svp-disc-min" class="svp-admin__input"/></label>
				<label data-svp-i18n="discountMaxOrder">حداکثر مبلغ سفارش (تومان، اختیاری)<input type="text" id="svp-disc-max-order" class="svp-admin__input"/></label>
				<label data-svp-i18n="discountMaxDiscount">سقف تخفیف (تومان، اختیاری)<input type="text" id="svp-disc-max-disc" class="svp-admin__input"/></label>
				<label data-svp-i18n="discountPlanIds">شناسه پلن‌های مجاز (با کاما، خالی = همه)<input type="text" id="svp-disc-plans" class="svp-admin__input" placeholder="1,2,3"/></label>
				<label data-svp-i18n="discountRestrictedUser">محدود به کاربر (شناسه، اختیاری)<input type="number" id="svp-disc-user" min="0" class="svp-admin__input"/></label>
				<div class="svp-admin__hint" data-svp-i18n="discountAllowSection">مجاز برای:</div>
				<label class="svp-admin__check"><input type="checkbox" id="svp-disc-allow-new" checked/> <span data-svp-i18n="discountAllowNew">خرید جدید</span></label>
				<label class="svp-admin__check"><input type="checkbox" id="svp-disc-allow-renew" checked/> <span data-svp-i18n="discountAllowRenew">تمدید</span></label>
				<label class="svp-admin__check"><input type="checkbox" id="svp-disc-allow-vol" checked/> <span data-svp-i18n="discountAllowVol">افزایش حجم</span></label>
				<label class="svp-admin__check"><input type="checkbox" id="svp-disc-allow-users" checked/> <span data-svp-i18n="discountAllowUsers">افزایش کاربر</span></label>
				<label class="svp-admin__check"><input type="checkbox" id="svp-disc-active" checked/> <span data-svp-i18n="discountActive">فعال</span></label>
				<button type="button" class="svp-btn svp-btn--primary" data-svp-admin-op="discount_save" data-svp-i18n="discountSave">ذخیره کد</button>
				<label data-svp-i18n="discountIdDelete">شناسه برای حذف<input type="number" id="svp-disc-del-id" min="1" class="svp-admin__input"/></label>
				<button type="button" class="svp-btn" data-svp-admin-op="discount_delete" data-svp-i18n="discountDelete">حذف با شناسه</button>
				<pre class="svp-admin__out" id="svp-adm-disc"></pre>
			</section>
			<section class="svp-admin__card" data-svp-portal-site-only>
				<h2>تنظیمات کریپتو (NOWPayments)</h2>
				<label>API key<textarea id="svp-cry-api" class="svp-admin__textarea" rows="2"></textarea></label>
				<label>IPN secret<textarea id="svp-cry-ipn" class="svp-admin__textarea" rows="2"></textarea></label>
				<label>pay_currency<input type="text" id="svp-cry-cur" class="svp-admin__input" placeholder="usdttrc20"/></label>
				<button type="button" class="svp-btn svp-btn--primary" data-svp-admin-op="save_crypto">ذخیره</button>
				<button type="button" class="svp-btn" data-svp-admin-op="rotate_ipn_path">تولید مسیر جدید IPN</button>
				<pre class="svp-admin__out" id="svp-adm-cry"></pre>
			</section>
		</div>
		
<script src="{{ asset('portal/portal.js') }}?v={{ $assetVersion }}"></script>
</body>
</html>