import { useEffect } from 'react'
import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { useAuth } from './lib/auth'
import Shell from './components/Shell'
import Guard from './components/Guard'
import HomePage from './features/catalog/HomePage'
import CatalogPage from './features/catalog/CatalogPage'
import ProductPage from './features/catalog/ProductPage'
import ShopsPage from './features/catalog/ShopsPage'
import ShopPage from './features/catalog/ShopPage'
import { LoginPage, RegisterPage } from './features/auth/AuthPages'
import CartPage from './features/checkout/CartPage'
import CheckoutPage from './features/checkout/CheckoutPage'
import OrdersPage from './features/orders/OrdersPage'
import OrderDetailPage from './features/orders/OrderDetailPage'
import DownloadsPage from './features/orders/DownloadsPage'
import AccountPage from './features/account/AccountPage'
import NotFoundPage from './features/account/NotFoundPage'
import SellerLayout from './features/seller/SellerLayout'
import SellerHome from './features/seller/SellerHome'
import ProductList from './features/seller/ProductList'
import ProductForm from './features/seller/ProductForm'
import SellerOrders from './features/seller/SellerOrders'
import ShopSettings from './features/seller/ShopSettings'
import AdminLayout from './features/admin/AdminLayout'
import AdminHome from './features/admin/AdminHome'
import ShopQueue from './features/admin/ShopQueue'
import ProductModeration from './features/admin/ProductModeration'
import PaymentQueue from './features/admin/PaymentQueue'

export default function App() {
  const bootstrap = useAuth((s) => s.bootstrap)

  useEffect(() => {
    bootstrap()
  }, [bootstrap])

  return (
    <BrowserRouter>
      <Routes>
        <Route element={<Shell />}>
          <Route index element={<HomePage />} />
          <Route path="produk" element={<CatalogPage />} />
          <Route path="produk/:slug" element={<ProductPage />} />
          <Route path="toko" element={<ShopsPage />} />
          <Route path="toko/:slug" element={<ShopPage />} />
          <Route path="masuk" element={<LoginPage />} />
          <Route path="daftar" element={<RegisterPage />} />
          <Route path="keranjang" element={<CartPage />} />

          <Route path="checkout" element={<Guard><CheckoutPage /></Guard>} />
          <Route path="pesanan" element={<Guard><OrdersPage /></Guard>} />
          <Route path="pesanan/:invoice" element={<Guard><OrderDetailPage /></Guard>} />
          <Route path="akun" element={<Guard><AccountPage /></Guard>} />
          <Route path="akun/unduhan" element={<Guard><DownloadsPage /></Guard>} />

          <Route
            path="seller"
            element={<Guard roles={['seller', 'admin']}><SellerLayout /></Guard>}
          >
            <Route index element={<SellerHome />} />
            <Route path="produk" element={<ProductList />} />
            <Route path="produk/baru" element={<ProductForm />} />
            <Route path="produk/:id" element={<ProductForm />} />
            <Route path="pesanan" element={<SellerOrders />} />
            <Route path="toko" element={<ShopSettings />} />
          </Route>

          <Route path="admin" element={<Guard roles={['admin']}><AdminLayout /></Guard>}>
            <Route index element={<AdminHome />} />
            <Route path="toko" element={<ShopQueue />} />
            <Route path="produk" element={<ProductModeration />} />
            <Route path="pembayaran" element={<PaymentQueue />} />
          </Route>

          <Route path="*" element={<NotFoundPage />} />
        </Route>
      </Routes>
    </BrowserRouter>
  )
}
