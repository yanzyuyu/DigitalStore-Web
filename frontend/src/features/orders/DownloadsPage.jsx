import { useEffect, useState } from 'react'
import { fetchDownloads } from './api'
import { apiError, downloadFile } from '../../lib/api'
import { dateTime } from '../../lib/format'
import { Alert, Empty, Spinner } from '../../components/ui'
import Icon from '../../components/Icon'

export default function DownloadsPage() {
  const [page, setPage] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    fetchDownloads()
      .then(setPage)
      .catch((err) => setError(apiError(err, 'Daftar unduhan gagal dimuat.')))
  }, [])

  async function download(item) {
    setError('')
    try {
      await downloadFile(`/api/v1/downloads/${item.id}/file`, item.file?.name)
    } catch (err) {
      setError(err.userMessage || apiError(err, 'Unduhan ditolak. Akses mungkin sudah dicabut.'))
    }
  }

  if (!page && !error) return <Spinner />

  return (
    <div>
      <h1 className="font-display text-3xl text-ink">Unduhan</h1>
      <p className="mt-1 text-sm text-muted">
        Setiap unduhan diperiksa ulang. Refund mencabut akses seketika.
      </p>
      {error ? <div className="mt-4"><Alert>{error}</Alert></div> : null}
      {page?.items.length === 0 ? (
        <div className="mt-8">
          <Empty title="Belum ada file" body="File muncul setelah pembayaran diverifikasi." />
        </div>
      ) : (
        <ul className="mt-6 divide-y divide-line border-y border-line">
          {page?.items.map((item) => (
            <li key={item.id} className="flex flex-wrap items-center justify-between gap-3 py-4">
              <div>
                <p className="text-sm font-medium text-ink">{item.file?.name ?? 'File'}</p>
                <p className="text-xs text-muted">
                  {item.file?.human_size}
                  {item.last_downloaded_at ? ` · terakhir ${dateTime(item.last_downloaded_at)}` : ''}
                  {` · ${item.download_count}× diunduh`}
                </p>
              </div>
              <button
                type="button"
                onClick={() => download(item)}
                className="inline-flex items-center gap-2 rounded-sm border border-line px-3 py-2 text-sm hover:bg-paper-2"
              >
                <Icon name="download" />
                Unduh
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
