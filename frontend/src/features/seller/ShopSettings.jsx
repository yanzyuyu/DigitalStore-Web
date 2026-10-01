import { useEffect, useState } from 'react'
import { fetchMyShop, updateShop } from './api'
import { useAuth } from '../../lib/auth'
import { apiError, fieldErrors } from '../../lib/api'
import { Alert, Button, Field, Spinner, inputClass } from '../../components/ui'

export default function ShopSettings() {
  const refresh = useAuth((s) => s.refresh)
  const [shop, setShop] = useState(null)
  const [error, setError] = useState('')
  const [fields, setFields] = useState({})
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    fetchMyShop()
      .then(setShop)
      .catch((err) => setError(apiError(err, 'Toko gagal dimuat.')))
  }, [])

  if (error && !shop) return <p className="text-sm text-red-700">{error}</p>
  if (!shop) return <Spinner />

  async function onSubmit(event) {
    event.preventDefault()
    setBusy(true)
    setError('')
    setNotice('')
    setFields({})
    const form = new FormData(event.currentTarget)
    try {
      const updated = await updateShop({
        name: form.get('name'),
        description: form.get('description'),
        bank_name: form.get('bank_name'),
        bank_account_number: form.get('bank_account_number'),
        bank_account_holder: form.get('bank_account_holder'),
      })
      setShop(updated)
      setNotice('Toko diperbarui.')
      await refresh()
    } catch (err) {
      setFields(fieldErrors(err))
      setError(apiError(err, 'Gagal menyimpan.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <form onSubmit={onSubmit} className="max-w-xl space-y-4">
      <h1 className="font-display text-3xl text-ink">Pengaturan toko</h1>
      {notice ? <Alert tone="ok">{notice}</Alert> : null}
      {error ? <Alert>{error}</Alert> : null}
      <Field label="Nama toko" error={fields.name}>
        <input name="name" required defaultValue={shop.name} className={inputClass} />
      </Field>
      <Field label="Deskripsi" error={fields.description}>
        <textarea name="description" rows={4} defaultValue={shop.description ?? ''} className={inputClass} />
      </Field>
      <fieldset className="space-y-4 rounded-sm border border-line p-4">
        <legend className="px-1 text-sm font-medium text-ink">Rekening pencairan</legend>
        <Field label="Bank" error={fields.bank_name}>
          <input name="bank_name" defaultValue={shop.bank?.name ?? ''} className={inputClass} />
        </Field>
        <Field label="Nomor rekening" error={fields.bank_account_number}>
          <input name="bank_account_number" defaultValue={shop.bank?.account_number ?? ''} className={inputClass} />
        </Field>
        <Field label="Atas nama" error={fields.bank_account_holder}>
          <input name="bank_account_holder" defaultValue={shop.bank?.account_holder ?? ''} className={inputClass} />
        </Field>
      </fieldset>
      <Button type="submit" tone="accent" disabled={busy}>
        {busy ? 'Menyimpan…' : 'Simpan'}
      </Button>
    </form>
  )
}
