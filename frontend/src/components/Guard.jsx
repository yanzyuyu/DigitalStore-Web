import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '../lib/auth'
import { Spinner } from './ui'

export default function Guard({ roles, children }) {
  const user = useAuth((s) => s.user)
  const ready = useAuth((s) => s.ready)
  const location = useLocation()

  if (!ready) return <Spinner />
  if (!user) {
    return <Navigate to="/masuk" replace state={{ from: location.pathname }} />
  }
  if (roles && !roles.includes(user.role)) {
    return <Navigate to="/" replace />
  }
  return children
}
