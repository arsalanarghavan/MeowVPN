import { useState, useEffect } from 'react'
import axios from 'axios'
import { CheckCircle2, XCircle, Loader2, AlertCircle, ChevronRight, ChevronLeft } from 'lucide-react'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

const API_URL = import.meta.env.VITE_API_URL || 'http://api.localhost'

// Configure axios defaults with timeout
axios.defaults.timeout = 30000 // 30 seconds for normal requests

// Retry function for API calls
const retryRequest = async (
  fn: () => Promise<any>,
  retries: number = 3,
  delay: number = 1000
): Promise<any> => {
  for (let i = 0; i < retries; i++) {
    try {
      return await fn()
    } catch (error: any) {
      if (i === retries - 1) throw error
      // Don't retry on 4xx errors (client errors)
      if (error.response && error.response.status >= 400 && error.response.status < 500) {
        throw error
      }
      await new Promise(resolve => setTimeout(resolve, delay * (i + 1)))
    }
  }
}

// Create axios instance with longer timeout for SSL operations
const sslAxios = axios.create({
  timeout: 300000, // 5 minutes for SSL installation
  baseURL: API_URL
})

interface SetupStep {
  id: number
  title: string
  description: string
  component: React.ComponentType<SetupStepProps>
}

interface SetupStepProps {
  onNext: (data: any) => void
  onBack: () => void
  data: any
  errors: Record<string, string>
  setErrors: (errors: Record<string, string>) => void
}

interface ValidationError {
  field: string
  message: string
}

const steps: SetupStep[] = [
  { 
    id: 1, 
    title: 'تنظیمات دیتابیس', 
    description: 'اطلاعات اتصال به پایگاه داده PostgreSQL را وارد کنید',
    component: DatabaseStep 
  },
  { 
    id: 2, 
    title: 'تنظیمات Redis', 
    description: 'اطلاعات اتصال به Redis را وارد کنید',
    component: RedisStep 
  },
  { 
    id: 3, 
    title: 'دامنه‌ها و SSL', 
    description: 'دامنه‌های API، Panel و Subscription را تنظیم و SSL را نصب کنید',
    component: DomainSSLStep 
  },
  { 
    id: 4, 
    title: 'اکانت ادمین', 
    description: 'حساب کاربری مدیر سیستم را ایجاد کنید',
    component: AdminStep 
  },
  { 
    id: 5, 
    title: 'ربات تلگرام', 
    description: 'توکن ربات تلگرام را وارد کنید',
    component: BotStep 
  },
  { 
    id: 6, 
    title: 'تکمیل', 
    description: 'بررسی نهایی و تکمیل راه‌اندازی',
    component: CompleteStep 
  },
]

export default function SetupWizard() {
  const [currentStep, setCurrentStep] = useState(0)
  const [formData, setFormData] = useState<any>({})
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [loading, setLoading] = useState(false)

  const handleNext = (data: any) => {
    const newData = { ...formData, ...data }
    setFormData(newData)
    setErrors({})
    if (currentStep < steps.length - 1) {
      setCurrentStep(currentStep + 1)
    }
  }

  const handleBack = () => {
    setErrors({})
    if (currentStep > 0) {
      setCurrentStep(currentStep - 1)
    }
  }

  const CurrentStepComponent = steps[currentStep].component
  const progress = ((currentStep + 1) / steps.length) * 100

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 flex items-center justify-center p-4" dir="rtl">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden">
        {/* Header */}
        <div className="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6">
          <h1 className="text-3xl font-bold mb-2">راه‌اندازی MeowVPN</h1>
          <p className="text-blue-100">مرحله {currentStep + 1} از {steps.length}</p>
        </div>

        {/* Progress Bar */}
        <div className="px-6 pt-6">
          <div className="w-full bg-gray-200 rounded-full h-2 mb-4">
            <div 
              className="bg-gradient-to-r from-blue-600 to-purple-600 h-2 rounded-full transition-all duration-300"
              style={{ width: `${progress}%` }}
            />
          </div>
          <div className="flex justify-between text-sm text-gray-600 mb-6">
            {steps.map((step, index) => (
              <div
                key={step.id}
                className={clsx(
                  "flex flex-col items-center flex-1",
                  index <= currentStep ? "text-blue-600" : "text-gray-400"
                )}
              >
                <div
                  className={clsx(
                    "w-8 h-8 rounded-full flex items-center justify-center mb-2 transition-all",
                    index < currentStep && "bg-green-500 text-white",
                    index === currentStep && "bg-blue-600 text-white ring-4 ring-blue-200",
                    index > currentStep && "bg-gray-200 text-gray-400"
                  )}
                >
                  {index < currentStep ? (
                    <CheckCircle2 className="w-5 h-5" />
                  ) : (
                    <span>{step.id}</span>
                  )}
                </div>
                <span className="text-xs text-center hidden sm:block">{step.title}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Step Content */}
        <div className="px-6 pb-6">
          <div className="mb-4">
            <h2 className="text-2xl font-bold text-gray-800 mb-2">{steps[currentStep].title}</h2>
            <p className="text-gray-600">{steps[currentStep].description}</p>
          </div>

          <div className="bg-gray-50 rounded-lg p-6">
            <CurrentStepComponent
              onNext={handleNext}
              onBack={handleBack}
              data={formData}
              errors={errors}
              setErrors={setErrors}
            />
          </div>
        </div>

        {/* Footer Navigation */}
        <div className="px-6 py-4 bg-gray-50 border-t flex justify-between items-center">
          <button
            onClick={handleBack}
            disabled={currentStep === 0}
            className={clsx(
              "px-6 py-2 rounded-lg font-medium transition-all flex items-center gap-2",
              currentStep === 0
                ? "bg-gray-200 text-gray-400 cursor-not-allowed"
                : "bg-white text-gray-700 hover:bg-gray-100 border border-gray-300"
            )}
          >
            <ChevronRight className="w-5 h-5" />
            قبلی
          </button>
          <div className="text-sm text-gray-500">
            مرحله {currentStep + 1} از {steps.length}
          </div>
        </div>
      </div>
    </div>
  )
}

function DatabaseStep({ onNext, data, errors, setErrors }: SetupStepProps) {
  const [form, setForm] = useState(data.database || {
    host: 'postgres',
    port: '5432',
    database: 'meowvpn',
    username: 'meowvpn',
    password: ''
  })
  const [testing, setTesting] = useState(false)
  const [testResult, setTestResult] = useState<'success' | 'error' | null>(null)

  const validate = () => {
    const newErrors: Record<string, string> = {}
    if (!form.host) newErrors.host = 'آدرس سرور الزامی است'
    if (!form.port) newErrors.port = 'پورت الزامی است'
    if (!form.database) newErrors.database = 'نام دیتابیس الزامی است'
    if (!form.username) newErrors.username = 'نام کاربری الزامی است'
    if (!form.password) newErrors.password = 'رمز عبور الزامی است'
    
    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  const handleTest = async () => {
    if (!validate()) return
    
    setTesting(true)
    setTestResult(null)
    try {
      await retryRequest(() => axios.post(`${API_URL}/api/setup/database`, form))
      setTestResult('success')
    } catch (error: any) {
      setTestResult('error')
      const errorMessage = error.response?.data?.error || error.message || 'خطا در اتصال'
      setErrors({ connection: errorMessage })
    } finally {
      setTesting(false)
    }
  }

  const handleSubmit = () => {
    if (!validate()) return
    if (testResult !== 'success') {
      setErrors({ connection: 'لطفاً ابتدا اتصال را تست کنید' })
      return
    }
    onNext({ database: form })
  }

  return (
    <div className="space-y-4">
      <Input
        label="آدرس سرور"
        type="text"
        value={form.host}
        onChange={(e) => setForm({ ...form, host: e.target.value })}
        error={errors.host}
        placeholder="postgres"
      />
      <Input
        label="پورت"
        type="number"
        value={form.port}
        onChange={(e) => setForm({ ...form, port: e.target.value })}
        error={errors.port}
        placeholder="5432"
      />
      <Input
        label="نام دیتابیس"
        type="text"
        value={form.database}
        onChange={(e) => setForm({ ...form, database: e.target.value })}
        error={errors.database}
        placeholder="meowvpn"
      />
      <Input
        label="نام کاربری"
        type="text"
        value={form.username}
        onChange={(e) => setForm({ ...form, username: e.target.value })}
        error={errors.username}
        placeholder="meowvpn"
      />
      <Input
        label="رمز عبور"
        type="password"
        value={form.password}
        onChange={(e) => setForm({ ...form, password: e.target.value })}
        error={errors.password}
        placeholder="رمز عبور دیتابیس"
      />

      {errors.connection && (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2">
          <AlertCircle className="w-5 h-5" />
          <span>{errors.connection}</span>
        </div>
      )}

      {testResult === 'success' && (
        <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2">
          <CheckCircle2 className="w-5 h-5" />
          <span>اتصال با موفقیت برقرار شد</span>
        </div>
      )}

      <div className="flex gap-3 pt-4">
        <button
          onClick={handleTest}
          disabled={testing}
          className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        >
          {testing ? (
            <>
              <Loader2 className="w-4 h-4 animate-spin" />
              در حال تست...
            </>
          ) : (
            'تست اتصال'
          )}
        </button>
        <button
          onClick={handleSubmit}
          disabled={testResult !== 'success'}
          className="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        >
          بعدی
          <ChevronLeft className="w-4 h-4" />
        </button>
      </div>
    </div>
  )
}

function RedisStep({ onNext, onBack, data, errors, setErrors }: SetupStepProps) {
  const [form, setForm] = useState(data.redis || {
    host: 'redis',
    port: '6379',
    password: ''
  })
  const [testing, setTesting] = useState(false)
  const [testResult, setTestResult] = useState<'success' | 'error' | null>(null)

  const validate = () => {
    const newErrors: Record<string, string> = {}
    if (!form.host) newErrors.host = 'آدرس سرور الزامی است'
    if (!form.port) newErrors.port = 'پورت الزامی است'
    
    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  const handleTest = async () => {
    if (!validate()) return
    
    setTesting(true)
    setTestResult(null)
    try {
      await retryRequest(() => axios.post(`${API_URL}/api/setup/redis`, form))
      setTestResult('success')
    } catch (error: any) {
      setTestResult('error')
      const errorMessage = error.response?.data?.error || error.message || 'خطا در اتصال'
      setErrors({ connection: errorMessage })
    } finally {
      setTesting(false)
    }
  }

  const handleSubmit = () => {
    if (!validate()) return
    if (testResult !== 'success') {
      setErrors({ connection: 'لطفاً ابتدا اتصال را تست کنید' })
      return
    }
    onNext({ redis: form })
  }

  return (
    <div className="space-y-4">
      <Input
        label="آدرس سرور"
        type="text"
        value={form.host}
        onChange={(e) => setForm({ ...form, host: e.target.value })}
        error={errors.host}
        placeholder="redis"
      />
      <Input
        label="پورت"
        type="number"
        value={form.port}
        onChange={(e) => setForm({ ...form, port: e.target.value })}
        error={errors.port}
        placeholder="6379"
      />
      <Input
        label="رمز عبور (اختیاری)"
        type="password"
        value={form.password}
        onChange={(e) => setForm({ ...form, password: e.target.value })}
        error={errors.password}
        placeholder="رمز عبور Redis (در صورت وجود)"
      />

      {errors.connection && (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2">
          <AlertCircle className="w-5 h-5" />
          <span>{errors.connection}</span>
        </div>
      )}

      {testResult === 'success' && (
        <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2">
          <CheckCircle2 className="w-5 h-5" />
          <span>اتصال با موفقیت برقرار شد</span>
        </div>
      )}

      <div className="flex gap-3 pt-4">
        <button
          onClick={handleTest}
          disabled={testing}
          className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        >
          {testing ? (
            <>
              <Loader2 className="w-4 h-4 animate-spin" />
              در حال تست...
            </>
          ) : (
            'تست اتصال'
          )}
        </button>
        <button
          onClick={handleSubmit}
          disabled={testResult !== 'success'}
          className="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        >
          بعدی
          <ChevronLeft className="w-4 h-4" />
        </button>
      </div>
    </div>
  )
}

function DomainSSLStep({ onNext, onBack, data, errors, setErrors }: SetupStepProps) {
  const [form, setForm] = useState(data.domains || {
    api_domain: '',
    panel_domain: '',
    subscription_domain: '',
    email: ''
  })
  const [savingDomains, setSavingDomains] = useState(false)
  const [installingSSL, setInstallingSSL] = useState(false)
  const [domainsSaved, setDomainsSaved] = useState(false)
  const [sslInstalled, setSslInstalled] = useState(false)
  const [sslError, setSslError] = useState<string | null>(null)
  const [sslProgress, setSslProgress] = useState(0)

  const validate = () => {
    const newErrors: Record<string, string> = {}
    if (!form.api_domain) newErrors.api_domain = 'دامنه API الزامی است'
    if (!form.panel_domain) newErrors.panel_domain = 'دامنه Panel الزامی است'
    if (!form.subscription_domain) newErrors.subscription_domain = 'دامنه Subscription الزامی است'
    if (!form.email) {
      newErrors.email = 'ایمیل برای SSL الزامی است'
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
      newErrors.email = 'فرمت ایمیل نامعتبر است'
    }
    
    // Basic domain validation
    const domainRegex = /^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i
    if (form.api_domain && !domainRegex.test(form.api_domain)) {
      newErrors.api_domain = 'فرمت دامنه نامعتبر است'
    }
    if (form.panel_domain && !domainRegex.test(form.panel_domain)) {
      newErrors.panel_domain = 'فرمت دامنه نامعتبر است'
    }
    if (form.subscription_domain && !domainRegex.test(form.subscription_domain)) {
      newErrors.subscription_domain = 'فرمت دامنه نامعتبر است'
    }
    
    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  const handleSaveDomains = async () => {
    if (!validate()) return
    
    setSavingDomains(true)
    setErrors({})
    try {
      await retryRequest(() => axios.post(`${API_URL}/api/setup/domains`, {
        api_domain: form.api_domain,
        panel_domain: form.panel_domain,
        subscription_domain: form.subscription_domain
      }))
      setDomainsSaved(true)
      
      // Automatically proceed to SSL installation
      setInstallingSSL(true)
      setSslError(null)
      setSslProgress(0)
      
      // Simulate progress (SSL installation can take time)
      const progressInterval = setInterval(() => {
        setSslProgress(prev => {
          if (prev >= 90) return prev // Don't go to 100% until done
          return prev + 5
        })
      }, 2000) // Update every 2 seconds
      
      try {
        const domains = [
          form.api_domain,
          form.panel_domain,
          form.subscription_domain
        ].filter(Boolean)
        
        // Use sslAxios with longer timeout for SSL installation
        await sslAxios.post('/api/setup/ssl', {
          email: form.email,
          domains: domains
        })
        clearInterval(progressInterval)
        setSslProgress(100)
        setSslInstalled(true)
      } catch (error: any) {
        clearInterval(progressInterval)
        setSslError(error.response?.data?.error || error.message || 'خطا در نصب SSL')
        // Allow user to retry SSL installation
        setInstallingSSL(false)
        setSslProgress(0)
      }
    } catch (error: any) {
      const errorMessage = error.response?.data?.error || error.message || 'خطا در ذخیره دامنه‌ها'
      setErrors({ domains: errorMessage })
    } finally {
      setSavingDomains(false)
    }
  }

  const handleSubmit = () => {
    if (sslInstalled) {
      onNext({ domains: form, ssl: { email: form.email, domains: [form.api_domain, form.panel_domain, form.subscription_domain] } })
    } else {
      setErrors({ ssl: 'لطفاً ابتدا SSL را نصب کنید' })
    }
  }

  return (
    <div className="space-y-4">
      <Input
        label="دامنه API"
        type="text"
        value={form.api_domain}
        onChange={(e) => setForm({ ...form, api_domain: e.target.value })}
        error={errors.api_domain}
        placeholder="api.yourdomain.com"
        disabled={domainsSaved}
      />
      <Input
        label="دامنه Panel"
        type="text"
        value={form.panel_domain}
        onChange={(e) => setForm({ ...form, panel_domain: e.target.value })}
        error={errors.panel_domain}
        placeholder="panel.yourdomain.com"
        disabled={domainsSaved}
      />
      <Input
        label="دامنه Subscription"
        type="text"
        value={form.subscription_domain}
        onChange={(e) => setForm({ ...form, subscription_domain: e.target.value })}
        error={errors.subscription_domain}
        placeholder="sub.yourdomain.com"
        disabled={domainsSaved}
      />
      <Input
        label="ایمیل برای SSL"
        type="email"
        value={form.email}
        onChange={(e) => setForm({ ...form, email: e.target.value })}
        error={errors.email}
        placeholder="admin@yourdomain.com"
        disabled={domainsSaved}
      />

      {domainsSaved && (
        <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2">
          <CheckCircle2 className="w-5 h-5" />
          <span>دامنه‌ها با موفقیت ذخیره شدند. در حال نصب SSL...</span>
        </div>
      )}

      {installingSSL && (
        <div className="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg">
          <div className="flex items-center gap-2 mb-2">
            <Loader2 className="w-5 h-5 animate-spin" />
            <span>در حال نصب SSL با Let's Encrypt. این ممکن است چند دقیقه طول بکشد...</span>
          </div>
          <div className="w-full bg-blue-200 rounded-full h-2 mt-2">
            <div 
              className="bg-blue-600 h-2 rounded-full transition-all duration-500"
              style={{ width: `${sslProgress}%` }}
            />
          </div>
          <p className="text-xs mt-1 text-blue-600">{sslProgress}%</p>
        </div>
      )}

      {sslInstalled && (
        <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2">
          <CheckCircle2 className="w-5 h-5" />
          <span>SSL با موفقیت نصب شد</span>
        </div>
      )}

      {(errors.domains || sslError) && (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2">
          <AlertCircle className="w-5 h-5" />
          <span>{errors.domains || sslError}</span>
        </div>
      )}

      <div className="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg text-sm">
        <p className="font-medium mb-1">نکته مهم:</p>
        <p>قبل از ادامه، مطمئن شوید که DNS دامنه‌ها به IP سرور شما اشاره می‌کند و پورت‌های 80 و 443 باز هستند.</p>
      </div>

      <div className="flex gap-3 pt-4">
        <button
          onClick={onBack}
          disabled={savingDomains || installingSSL}
          className="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        >
          <ChevronRight className="w-4 h-4" />
          قبلی
        </button>
        {!domainsSaved ? (
          <button
            onClick={handleSaveDomains}
            disabled={savingDomains}
            className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
          >
            {savingDomains ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin" />
                در حال ذخیره...
              </>
            ) : (
              <>
                ذخیره دامنه‌ها و نصب SSL
                <ChevronLeft className="w-4 h-4" />
              </>
            )}
          </button>
        ) : sslInstalled ? (
          <button
            onClick={handleSubmit}
            className="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center justify-center gap-2"
          >
            بعدی
            <ChevronLeft className="w-4 h-4" />
          </button>
        ) : (
          <div className="flex-1 px-4 py-2 bg-gray-300 text-gray-600 rounded-lg flex items-center justify-center gap-2">
            <Loader2 className="w-4 h-4 animate-spin" />
            در حال نصب SSL...
          </div>
        )}
      </div>
    </div>
  )
}

function SSLStep({ onNext, onBack, data, errors, setErrors }: SetupStepProps) {
  const [form, setForm] = useState(data.ssl || {
    email: '',
    domains: []
  })
  const [installing, setInstalling] = useState(false)
  const [installResult, setInstallResult] = useState<'success' | 'error' | null>(null)

  useEffect(() => {
    if (data.domains) {
      const domains = [
        data.domains.api_domain,
        data.domains.panel_domain,
        data.domains.subscription_domain
      ].filter(Boolean)
      setForm({ ...form, domains })
    }
  }, [data.domains])

  const validate = () => {
    const newErrors: Record<string, string> = {}
    if (!form.email) {
      newErrors.email = 'ایمیل الزامی است'
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
      newErrors.email = 'فرمت ایمیل نامعتبر است'
    }
    if (!form.domains || form.domains.length === 0) {
      newErrors.domains = 'حداقل یک دامنه باید انتخاب شود'
    }
    
    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  const handleInstallSSL = async () => {
    if (!validate()) return
    
    setInstalling(true)
    setInstallResult(null)
    try {
      await axios.post(`${API_URL}/api/setup/ssl`, {
        email: form.email,
        domains: form.domains
      })
      setInstallResult('success')
    } catch (error: any) {
      setInstallResult('error')
      const errorMessage = error.response?.data?.error || error.message || 'خطا در نصب SSL'
      setErrors({ ssl: errorMessage })
    } finally {
      setInstalling(false)
    }
  }

  const handleSubmit = () => {
    if (installResult === 'success') {
      onNext({ ssl: form })
    } else {
      setErrors({ ssl: 'لطفاً ابتدا SSL را نصب کنید' })
    }
  }

  return (
    <div className="space-y-4">
      <Input
        label="ایمیل برای SSL"
        type="email"
        value={form.email}
        onChange={(e) => setForm({ ...form, email: e.target.value })}
        error={errors.email}
        placeholder="admin@yourdomain.com"
      />

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          دامنه‌های انتخاب شده
        </label>
        <div className="space-y-2">
          {form.domains?.map((domain: string, index: number) => (
            <div key={index} className="bg-white border border-gray-300 rounded-lg px-4 py-2 flex items-center gap-2">
              <CheckCircle2 className="w-5 h-5 text-green-500" />
              <span className="text-gray-700">{domain}</span>
            </div>
          ))}
        </div>
      </div>

      {errors.ssl && (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2">
          <AlertCircle className="w-5 h-5" />
          <span>{errors.ssl}</span>
        </div>
      )}

      {installResult === 'success' && (
        <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2">
          <CheckCircle2 className="w-5 h-5" />
          <span>SSL با موفقیت نصب شد</span>
        </div>
      )}

      <div className="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg text-sm">
        <p className="font-medium mb-1">هشدار:</p>
        <p>قبل از نصب SSL، مطمئن شوید که DNS دامنه‌ها به IP سرور شما اشاره می‌کند و پورت‌های 80 و 443 باز هستند.</p>
      </div>

      <div className="flex gap-3 pt-4">
        <button
          onClick={onBack}
          className="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 flex items-center justify-center gap-2"
        >
          <ChevronRight className="w-4 h-4" />
          قبلی
        </button>
        <button
          onClick={handleInstallSSL}
          disabled={installing || installResult === 'success'}
          className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        >
          {installing ? (
            <>
              <Loader2 className="w-4 h-4 animate-spin" />
              در حال نصب SSL...
            </>
          ) : installResult === 'success' ? (
            <>
              <CheckCircle2 className="w-4 h-4" />
              SSL نصب شد
            </>
          ) : (
            'نصب SSL با Let\'s Encrypt'
          )}
        </button>
        {installResult === 'success' && (
          <button
            onClick={handleSubmit}
            className="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center justify-center gap-2"
          >
            بعدی
            <ChevronLeft className="w-4 h-4" />
          </button>
        )}
      </div>
    </div>
  )
}

function AdminStep({ onNext, onBack, data, errors, setErrors }: SetupStepProps) {
  const [form, setForm] = useState(data.admin || {
    username: '',
    email: '',
    password: '',
    password_confirmation: ''
  })

  const validate = () => {
    const newErrors: Record<string, string> = {}
    if (!form.username) newErrors.username = 'نام کاربری الزامی است'
    if (!form.email) {
      newErrors.email = 'ایمیل الزامی است'
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
      newErrors.email = 'فرمت ایمیل نامعتبر است'
    }
    if (!form.password) {
      newErrors.password = 'رمز عبور الزامی است'
    } else if (form.password.length < 8) {
      newErrors.password = 'رمز عبور باید حداقل 8 کاراکتر باشد'
    }
    if (form.password !== form.password_confirmation) {
      newErrors.password_confirmation = 'رمز عبور و تکرار آن یکسان نیستند'
    }
    
    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  const handleSubmit = () => {
    if (validate()) {
      onNext({ admin: form })
    }
  }

  return (
    <div className="space-y-4">
      <Input
        label="نام کاربری"
        type="text"
        value={form.username}
        onChange={(e) => setForm({ ...form, username: e.target.value })}
        error={errors.username}
        placeholder="admin"
      />
      <Input
        label="ایمیل"
        type="email"
        value={form.email}
        onChange={(e) => setForm({ ...form, email: e.target.value })}
        error={errors.email}
        placeholder="admin@yourdomain.com"
      />
      <Input
        label="رمز عبور"
        type="password"
        value={form.password}
        onChange={(e) => setForm({ ...form, password: e.target.value })}
        error={errors.password}
        placeholder="حداقل 8 کاراکتر"
      />
      <Input
        label="تکرار رمز عبور"
        type="password"
        value={form.password_confirmation}
        onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
        error={errors.password_confirmation}
        placeholder="تکرار رمز عبور"
      />

      <div className="flex gap-3 pt-4">
        <button
          onClick={onBack}
          className="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 flex items-center justify-center gap-2"
        >
          <ChevronRight className="w-4 h-4" />
          قبلی
        </button>
        <button
          onClick={handleSubmit}
          className="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center justify-center gap-2"
        >
          بعدی
          <ChevronLeft className="w-4 h-4" />
        </button>
      </div>
    </div>
  )
}

function BotStep({ onNext, onBack, data, errors, setErrors }: SetupStepProps) {
  const [form, setForm] = useState(data.bot || {
    bot_token: ''
  })

  const validate = () => {
    const newErrors: Record<string, string> = {}
    if (!form.bot_token) {
      newErrors.bot_token = 'توکن ربات الزامی است'
    } else if (!/^\d+:[A-Za-z0-9_-]+$/.test(form.bot_token)) {
      newErrors.bot_token = 'فرمت توکن نامعتبر است'
    }
    
    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  const handleSubmit = () => {
    if (validate()) {
      onNext({ bot: form })
    }
  }

  return (
    <div className="space-y-4">
      <Input
        label="توکن ربات تلگرام"
        type="text"
        value={form.bot_token}
        onChange={(e) => setForm({ ...form, bot_token: e.target.value })}
        error={errors.bot_token}
        placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
      />

      <div className="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg text-sm">
        <p className="font-medium mb-1">راهنمایی:</p>
        <p>برای دریافت توکن ربات، به @BotFather در تلگرام مراجعه کنید و ربات جدید ایجاد کنید.</p>
      </div>

      <div className="flex gap-3 pt-4">
        <button
          onClick={onBack}
          className="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 flex items-center justify-center gap-2"
        >
          <ChevronRight className="w-4 h-4" />
          قبلی
        </button>
        <button
          onClick={handleSubmit}
          className="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center justify-center gap-2"
        >
          بعدی
          <ChevronLeft className="w-4 h-4" />
        </button>
      </div>
    </div>
  )
}

function CompleteStep({ onBack, data, errors, setErrors }: SetupStepProps) {
  const [completing, setCompleting] = useState(false)
  const [completed, setCompleted] = useState(false)
  const [completionErrors, setCompletionErrors] = useState<string[]>([])
  const [currentStep, setCurrentStep] = useState('')

  const handleComplete = async () => {
    setCompleting(true)
    setCompletionErrors([])
    
    const encounteredErrors: string[] = []
    
    try {
      // Save all configuration step by step with correct endpoints
      // Note: domains and SSL are already saved in DomainSSLStep
      const steps = [
        { endpoint: '/api/setup/database/save', data: data.database, name: 'دیتابیس', required: true, skipIfEmpty: false },
        { endpoint: '/api/setup/redis/save', data: data.redis, name: 'Redis', required: true, skipIfEmpty: false },
        { endpoint: '/api/setup/bot', data: data.bot, name: 'ربات تلگرام', required: false, skipIfEmpty: true },
        { endpoint: '/api/setup/admin', data: data.admin, name: 'اکانت ادمین', required: true, skipIfEmpty: false },
        { endpoint: '/api/setup/complete', data: {}, name: 'تکمیل', required: false, skipIfEmpty: false }
      ]

      for (const step of steps) {
        // Skip if required data is missing
        if (step.required && !step.data && step.endpoint !== '/api/setup/complete') {
          encounteredErrors.push(`${step.name}: تنظیمات وارد نشده`)
          continue
        }
        
        // Skip if data is empty and skipIfEmpty is true
        if (step.skipIfEmpty && (!step.data || Object.keys(step.data).length === 0)) {
          continue
        }
        
        setCurrentStep(step.name)
        try {
          // Use retry logic for all API calls
          await retryRequest(() => axios.post(`${API_URL}${step.endpoint}`, step.data || {}), 3, 1000)
        } catch (error: any) {
          // Don't fail on 409 (already exists) or similar non-critical errors
          if (error.response?.status === 409) {
            continue // Already saved, skip
          }
          const errorMsg = error.response?.data?.error || error.message || 'خطای نامشخص'
          encounteredErrors.push(`${step.name}: ${errorMsg}`)
        }
      }

      setCompletionErrors(encounteredErrors)
      
      if (encounteredErrors.length === 0) {
        setCompleted(true)
        setTimeout(() => {
          window.location.reload()
        }, 2000)
      }
    } catch (error: any) {
      setCompletionErrors([error.response?.data?.error || error.message || 'خطا در تکمیل راه‌اندازی'])
    } finally {
      setCompleting(false)
      setCurrentStep('')
    }
  }

  if (completed) {
    return (
      <div className="text-center py-8">
        <CheckCircle2 className="w-16 h-16 text-green-500 mx-auto mb-4" />
        <h3 className="text-2xl font-bold text-gray-800 mb-2">راه‌اندازی با موفقیت تکمیل شد!</h3>
        <p className="text-gray-600 mb-4">در حال هدایت به داشبورد...</p>
        <Loader2 className="w-8 h-8 text-blue-600 mx-auto animate-spin" />
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="bg-white border border-gray-200 rounded-lg p-6">
        <h3 className="text-xl font-bold text-gray-800 mb-4">بررسی نهایی تنظیمات</h3>
        <div className="space-y-3">
          <ConfigItem label="دیتابیس" value={data.database?.host || 'تنظیم نشده'} />
          <ConfigItem label="Redis" value={data.redis?.host || 'تنظیم نشده'} />
          <ConfigItem label="دامنه API" value={data.domains?.api_domain || 'تنظیم نشده'} />
          <ConfigItem label="دامنه Panel" value={data.domains?.panel_domain || 'تنظیم نشده'} />
          <ConfigItem label="دامنه Subscription" value={data.domains?.subscription_domain || 'تنظیم نشده'} />
          <ConfigItem label="SSL" value={data.ssl ? 'نصب شده' : 'نصب نشده'} />
          <ConfigItem label="ربات تلگرام" value={data.bot?.bot_token ? 'تنظیم شده' : 'تنظیم نشده'} />
          <ConfigItem label="ایمیل ادمین" value={data.admin?.email || 'تنظیم نشده'} />
        </div>
      </div>

      {completionErrors.length > 0 && (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
          <p className="font-medium mb-2">خطاها:</p>
          <ul className="list-disc list-inside space-y-1">
            {completionErrors.map((error, index) => (
              <li key={index}>{error}</li>
            ))}
          </ul>
        </div>
      )}

      <div className="flex gap-3 pt-4">
        <button
          onClick={onBack}
          disabled={completing}
          className="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        >
          <ChevronRight className="w-4 h-4" />
          قبلی
        </button>
        <button
          onClick={handleComplete}
          disabled={completing}
          className="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        >
          {completing ? (
            <>
              <Loader2 className="w-4 h-4 animate-spin" />
              {currentStep ? `در حال پردازش ${currentStep}...` : 'در حال تکمیل...'}
            </>
          ) : (
            <>
              <CheckCircle2 className="w-4 h-4" />
              تکمیل راه‌اندازی
            </>
          )}
        </button>
      </div>
    </div>
  )
}

// Helper Components
function Input({ label, type, value, onChange, error, placeholder, disabled }: any) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      <input
        type={type}
        value={value}
        onChange={onChange}
        placeholder={placeholder}
        disabled={disabled}
        className={twMerge(
          "w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all",
          error ? "border-red-300 focus:ring-red-500 focus:border-red-500" : "border-gray-300",
          disabled ? "bg-gray-100 cursor-not-allowed opacity-60" : ""
        )}
      />
      {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
    </div>
  )
}

function ConfigItem({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
      <span className="text-gray-600">{label}:</span>
      <span className="text-gray-800 font-medium">{value}</span>
    </div>
  )
}
