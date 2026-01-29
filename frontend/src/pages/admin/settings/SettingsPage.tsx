import { useState, useEffect } from 'react'
import { Settings, Server, CreditCard, Bot, Shield, Save, RefreshCw } from 'lucide-react'

interface SystemSettings {
  affiliate_commission_rate: number
  affiliate_minimum_payout: number
  card_number: string
  card_holder: string
  bot_username: string
}

export default function SettingsPage() {
  const [settings, setSettings] = useState<SystemSettings>({
    affiliate_commission_rate: 10,
    affiliate_minimum_payout: 500000,
    card_number: '',
    card_holder: '',
    bot_username: '',
  })
  const [loading, setLoading] = useState(false)
  const [activeTab, setActiveTab] = useState<'general' | 'payment' | 'bot' | 'security'>('general')

  const handleSave = async () => {
    setLoading(true)
    try {
      // Save settings via API
      alert('تنظیمات ذخیره شد')
    } catch (error) {
      alert('خطا در ذخیره تنظیمات')
    } finally {
      setLoading(false)
    }
  }

  const tabs = [
    { id: 'general', label: 'عمومی', icon: Settings },
    { id: 'payment', label: 'پرداخت', icon: CreditCard },
    { id: 'bot', label: 'ربات تلگرام', icon: Bot },
    { id: 'security', label: 'امنیت', icon: Shield },
  ]

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-800">تنظیمات سیستم</h1>
        <button
          onClick={handleSave}
          disabled={loading}
          className="flex items-center gap-2 px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 disabled:opacity-50"
        >
          {loading ? <RefreshCw className="w-5 h-5 animate-spin" /> : <Save className="w-5 h-5" />}
          ذخیره تغییرات
        </button>
      </div>

      <div className="flex gap-6">
        {/* Sidebar */}
        <div className="w-64 flex-shrink-0">
          <nav className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id as any)}
                className={`w-full flex items-center gap-3 px-4 py-3 text-right transition-colors ${
                  activeTab === tab.id
                    ? 'bg-emerald-50 text-emerald-600 border-r-4 border-emerald-500'
                    : 'text-slate-600 hover:bg-slate-50'
                }`}
              >
                <tab.icon className="w-5 h-5" />
                <span>{tab.label}</span>
              </button>
            ))}
          </nav>
        </div>

        {/* Content */}
        <div className="flex-1 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
          {activeTab === 'general' && (
            <div className="space-y-6">
              <h2 className="text-lg font-semibold text-slate-800 mb-4">تنظیمات عمومی</h2>
              
              <div className="grid grid-cols-2 gap-6">
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-2">
                    درصد کمیسیون بازاریابی
                  </label>
                  <div className="flex items-center gap-2">
                    <input
                      type="number"
                      value={settings.affiliate_commission_rate}
                      onChange={(e) => setSettings({ ...settings, affiliate_commission_rate: Number(e.target.value) })}
                      className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                      min="0"
                      max="100"
                    />
                    <span className="text-slate-500">%</span>
                  </div>
                  <p className="mt-1 text-sm text-slate-500">
                    درصدی که از هر خرید به بازاریاب تعلق می‌گیرد
                  </p>
                </div>

                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-2">
                    حداقل مبلغ تسویه (ریال)
                  </label>
                  <input
                    type="number"
                    value={settings.affiliate_minimum_payout}
                    onChange={(e) => setSettings({ ...settings, affiliate_minimum_payout: Number(e.target.value) })}
                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    min="0"
                  />
                  <p className="mt-1 text-sm text-slate-500">
                    حداقل موجودی برای درخواست تسویه
                  </p>
                </div>
              </div>
            </div>
          )}

          {activeTab === 'payment' && (
            <div className="space-y-6">
              <h2 className="text-lg font-semibold text-slate-800 mb-4">تنظیمات پرداخت</h2>
              
              <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p className="text-sm text-yellow-800">
                  ⚠️ این اطلاعات برای پرداخت کارت به کارت استفاده می‌شود و به کاربران نمایش داده می‌شود.
                </p>
              </div>

              <div className="grid grid-cols-2 gap-6">
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-2">
                    شماره کارت
                  </label>
                  <input
                    type="text"
                    value={settings.card_number}
                    onChange={(e) => setSettings({ ...settings, card_number: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-mono"
                    placeholder="6037-XXXX-XXXX-XXXX"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-2">
                    نام صاحب کارت
                  </label>
                  <input
                    type="text"
                    value={settings.card_holder}
                    onChange={(e) => setSettings({ ...settings, card_holder: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    placeholder="نام و نام خانوادگی"
                  />
                </div>
              </div>

              <div className="border-t border-slate-200 pt-6">
                <h3 className="text-md font-medium text-slate-700 mb-4">درگاه زیبال</h3>
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-2">
                    Merchant ID
                  </label>
                  <input
                    type="text"
                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-mono"
                    placeholder="zibal-merchant-id"
                  />
                </div>
              </div>
            </div>
          )}

          {activeTab === 'bot' && (
            <div className="space-y-6">
              <h2 className="text-lg font-semibold text-slate-800 mb-4">تنظیمات ربات تلگرام</h2>
              
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-2">
                  توکن ربات
                </label>
                <input
                  type="password"
                  className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-mono"
                  placeholder="123456:ABC-DEF..."
                />
                <p className="mt-1 text-sm text-slate-500">
                  توکن دریافتی از @BotFather
                </p>
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-2">
                  نام کاربری ربات
                </label>
                <div className="flex items-center gap-2">
                  <span className="text-slate-400">@</span>
                  <input
                    type="text"
                    value={settings.bot_username}
                    onChange={(e) => setSettings({ ...settings, bot_username: e.target.value })}
                    className="flex-1 px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    placeholder="YourBotUsername"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-2">
                  آیدی یا نام کاربری پشتیبانی
                </label>
                <input
                  type="text"
                  className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                  placeholder="@support"
                />
              </div>
            </div>
          )}

          {activeTab === 'security' && (
            <div className="space-y-6">
              <h2 className="text-lg font-semibold text-slate-800 mb-4">تنظیمات امنیتی</h2>
              
              <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p className="text-sm text-blue-800">
                  🔒 تنظیمات امنیتی حساس هستند. با دقت تغییر دهید.
                </p>
              </div>

              <div className="space-y-4">
                <div className="flex items-center justify-between p-4 border border-slate-200 rounded-lg">
                  <div>
                    <p className="font-medium text-slate-800">Rate Limiting</p>
                    <p className="text-sm text-slate-500">محدودیت درخواست‌ها برای جلوگیری از حملات</p>
                  </div>
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" className="sr-only peer" defaultChecked />
                    <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                  </label>
                </div>

                <div className="flex items-center justify-between p-4 border border-slate-200 rounded-lg">
                  <div>
                    <p className="font-medium text-slate-800">Webhook Security</p>
                    <p className="text-sm text-slate-500">بررسی IP تلگرام برای webhook</p>
                  </div>
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" className="sr-only peer" defaultChecked />
                    <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                  </label>
                </div>

                <div className="flex items-center justify-between p-4 border border-slate-200 rounded-lg">
                  <div>
                    <p className="font-medium text-slate-800">رمزنگاری پسوردها</p>
                    <p className="text-sm text-slate-500">رمزنگاری پسورد سرورها در دیتابیس</p>
                  </div>
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" className="sr-only peer" defaultChecked />
                    <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                  </label>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

