import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import {
  createProduct,
  deleteProductFile,
  fetchProduct,
  fetchProductFiles,
  updateProduct,
  uploadProductFile,
} from './api'
import { apiError, fieldErrors } from '../../lib/api'
import { Alert, Button, Field, Spinner, inputClass } from '../../components/ui'

export default function ProductForm() {
  const { id } = useParams()
  const editing = Boolean(id)
  const navigate = useNavigate()
  const [product, setProduct] = useState(null)
  const [files, setFiles] = useState([])
  const [error, setError] = useState('')
  const [fields, setFields] = useState({})
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)
  const [loading, setLoading] = useState(editing)

  useEffect(() => {
    if (!editing) return
    fetchProduct(id)
      .then(setProduct)
      .catch((err) => setError(apiError(err, 'Produk tidak ditemukan di toko Anda.')))
      .finally(() => setLoading(false))
    fetchProductFiles(id).then(setFiles).catch(() => setFiles([]))
  }, [id, editing])

  async function onSubmit(event) {
    event.preventDefault()
    setBusy(true)
    setError('')
    setFields({})
    const form = new FormData(event.currentTarget)
    const payload = {
      name: form.get('name'),
      short_description: form.get('short_description') || null,
      description: form.get('description') || null,
      price: form.get('price'),
      compare_at_price: form.get('compare_at_price') || null,
      status: form.get('status'),
    }
    try {
      if (editing) {
        const updated = await updateProduct(id, payload)
        setProduct(updated)
        setNotice('Produk diperbarui.')
      } else {
        const created = await createProduct(payload)
        navigate(`/seller/produk/${created.id}`, { replace: true })
      }
    } catch (err) {
      setFields(fieldErrors(err))
      setError(apiError(err, 'Gagal menyimpan produk.'))
    } finally {
      setBusy(false)
    }
  }

  async function onFile(event) {
    const file = event.target.files?.[0]
    if (!file) return
    setBusy(true)
    setError('')
    try {
      const stored = await uploadProductFile(id, file, { name: file.name })
      setFiles((current) => [...current, stored])
      setNotice('File terunggah ke penyimpanan privat.')
    } catch (err) {
      setError(apiError(err, 'Gagal mengunggah file.'))
    } finally {
      setBusy(false)
      event.target.value = ''
    }
  }

  async function onRemoveFile(file) {
    try {
      await deleteProductFile(id, file.id)
      setFiles((current) => current.filter((item) => item.id !== file.id))
    } catch (err) {
      setError(apiError(err, 'Gagal menghapus file.'))
    }
  }

  if (loading) return <Spinner />

  const value = product ?? {}

  return (
    <div className="max-w-xl">
      <h1 className="font-display text-3xl text-ink">{editing ? 'Edit produk' : 'Produk baru'}</h1>
      <p className="mt-2 text-sm text-muted">
        Penjual tidak bisa menerbitkan sendiri. Pilih "Ajukan review" bila sudah siap.
      </p>
      <form onSubmit={onSubmit} className="mt-6 space-y-4">
        {notice ? <Alert tone="ok">{notice}</Alert> : null}
        {error ? <Alert>{error}</Alert> : null}
        <Field label="Nama" error={fields.name}>
          <input name="name" required minLength={3} defaultValue={value.name ?? ''} className={inputClass} />
        </Field>
        <Field label="Ringkasan" error={fields.short_description}>
          <input name="short_description" maxLength={500} defaultValue={value.short_description ?? ''} className={inputClass} />
        </Field>
        <Field label="Deskripsi" error={fields.description}>
          <textarea name="description" rows={6} defaultValue={value.description ?? ''} className={inputClass} />
        </Field>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Harga (Rp)" error={fields.price}>
            <input name="price" type="number" min={0} step="1" required defaultValue={value.price ?? ''} className={inputClass} />
          </Field>
          <Field label="Harga coret" error={fields.compare_at_price} hint="Harus ≥ harga jual">
            <input name="compare_at_price" type="number" min={0} step="1" defaultValue={value.compare_at_price ?? ''} className={inputClass} />
          </Field>
        </div>
        <Field label="Status" error={fields.status}>
          <select
            name="status"
            defaultValue={['draft', 'pending', 'archived'].includes(value.status) ? value.status : 'draft'}
            className={inputClass}
          >
            <option value="draft">Draf</option>
            <option value="pending">Ajukan review</option>
            {editing ? <option value="archived">Arsipkan</option> : null}
          </select>
        </Field>
        <Button type="submit" tone="accent" disabled={busy}>
          {busy ? 'Menyimpan…' : editing ? 'Simpan perubahan' : 'Buat draf'}
        </Button>
      </form>

      {editing ? (
        <section className="mt-10 border-t border-line pt-6">
          <h2 className="font-display text-xl text-ink">File unduhan</h2>
          <p className="mt-1 text-xs text-muted">
            Maksimal 200 MB. File tidak pernah punya URL publik.
          </p>
          <ul className="mt-4 divide-y divide-line border-y border-line">
            {files.map((file) => (
              <li key={file.id} className="flex items-center justify-between gap-3 py-2 text-sm">
                <span>
                  {file.name}
                  <span className="ml-2 text-xs text-muted">{file.human_size} · v{file.version}</span>
                </span>
                <button type="button" onClick={() => onRemoveFile(file)} className="text-xs text-muted hover:text-red-700">
                  Hapus
                </button>
              </li>
            ))}
            {files.length === 0 ? <li className="py-3 text-sm text-muted">Belum ada file.</li> : null}
          </ul>
          <label className="mt-4 block">
            <span className="sr-only">Unggah file produk</span>
            <input type="file" disabled={busy} onChange={onFile} className="text-sm" />
          </label>
        </section>
      ) : null}
    </div>
  )
}
