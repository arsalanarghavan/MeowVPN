import { MessageCircle } from 'lucide-react'

export default function BotOnlyPage() {
  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-100" dir="rtl">
      <div className="max-w-md mx-auto text-center p-8 bg-white rounded-2xl shadow-lg border border-slate-200">
        <div className="p-4 bg-amber-100 rounded-full w-fit mx-auto mb-6">
          <MessageCircle className="w-12 h-12 text-amber-600" />
        </div>
        <h1 className="text-xl font-bold text-slate-800 mb-2">دسترسی فقط از طریق ربات</h1>
        <p className="text-slate-600">
          این نقش کاربری از طریق پنل وب قابل استفاده نیست. برای مدیریت سرویس‌ها، خرید و پشتیبانی از ربات تلگرام استفاده کنید.
        </p>
      </div>
    </div>
  )
}
