import { Routes, Route, Navigate } from 'react-router-dom'
import AdminLayout from '../../components/layout/AdminLayout'
import Overview from './Overview'
import UsersPage from './users/UsersPage'
import ServersPage from './servers/ServersPage'
import PlansPage from './plans/PlansPage'
import TransactionsPage from './transactions/TransactionsPage'
import SubscriptionsPage from './subscriptions/SubscriptionsPage'
import ResellersPage from './resellers/ResellersPage'
import AffiliatesPage from './affiliates/AffiliatesPage'
import TicketsPage from './tickets/TicketsPage'
import InvoicesPage from './invoices/InvoicesPage'
import SettingsPage from './settings/SettingsPage'

export default function AdminDashboard() {
  return (
    <Routes>
      <Route element={<AdminLayout />}>
        <Route index element={<Overview />} />
        <Route path="users" element={<UsersPage />} />
        <Route path="servers" element={<ServersPage />} />
        <Route path="plans" element={<PlansPage />} />
        <Route path="subscriptions" element={<SubscriptionsPage />} />
        <Route path="transactions" element={<TransactionsPage />} />
        <Route path="resellers" element={<ResellersPage />} />
        <Route path="affiliates" element={<AffiliatesPage />} />
        <Route path="tickets" element={<TicketsPage />} />
        <Route path="invoices" element={<InvoicesPage />} />
        <Route path="settings" element={<SettingsPage />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>
    </Routes>
  )
}
