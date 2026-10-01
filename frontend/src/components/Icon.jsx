const paths = {
  search: 'M21 21l-4.3-4.3M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16z',
  cart: 'M6 6h15l-1.5 9h-12L6 6zm0 0L5 3H2M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm8 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2z',
  user: 'M20 21a8 8 0 0 0-16 0M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
  arrow: 'M5 12h14M13 6l6 6-6 6',
  download: 'M12 3v12m0 0l-4-4m4 4l4-4M4 21h16',
  check: 'M5 12l5 5L20 7',
  close: 'M6 6l12 12M18 6L6 18',
  plus: 'M12 5v14M5 12h14',
  store: 'M3 9l1-5h16l1 5M4 9h16v11H4V9zm4 0v11m4-11v11',
  file: 'M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5zM14 3v5h5',
  alert: 'M12 9v4m0 4h.01M10.3 4.3l-7 12.2A2 2 0 0 0 5 19.5h14a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0z',
}

export default function Icon({ name, className = 'h-4 w-4' }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden="true"
    >
      <path d={paths[name] ?? paths.file} />
    </svg>
  )
}
