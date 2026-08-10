/**
 * Interactive notification bell for the PublicLayout header.
 *
 * Fetches unread count from GET /notifications/unread-count on mount and polled
 * every 60s. Tapping opens a dropdown panel showing the 10 most recent
 * notifications. Clicking a booking-related notification deep-links to the
 * booking detail page.
 */
import { Bell, BellRing } from 'lucide-react';
import { useEffect, useState } from 'react';

interface NotificationItem {
  id: number;
  type: string;
  title: string;
  body: string;
  data: { bookingId?: number; bookingNumber?: string; vehicleName?: string } | null;
  read_at: string | null;
  created_at: string;
}

function formatRelative(dateStr: string): string {
  const date = new Date(dateStr);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMin = Math.floor(diffMs / 60000);
  if (diffMin < 1) return 'Just now';
  if (diffMin < 60) return `${diffMin}m ago`;
  const diffHours = Math.floor(diffMin / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  const diffDays = Math.floor(diffHours / 24);
  return `${diffDays}d ago`;
}

function notificationIconClass(type: string): string {
  switch (type) {
    case 'booking_confirmed': return 'text-success';
    case 'booking_cancelled': return 'text-error';
    case 'vehicle_checked_out': return 'text-primary';
    case 'vehicle_returned': return 'text-success';
    default: return 'text-textMuted';
  }
}

export default function HeaderNotificationBell() {
  const [unread, setUnread] = useState(0);
  const [open, setOpen] = useState(false);
  const [items, setItems] = useState<NotificationItem[]>([]);
  const [loading, setLoading] = useState(false);

  const fetchUnread = async () => {
    try {
      const res = await fetch('/notifications/unread-count', {
        headers: { Accept: 'application/json' },
      });
      if (res.ok) {
        const data = await res.json();
        setUnread(data.count ?? 0);
      }
    } catch { /* degrade silently */ }
  };

  const loadItems = async () => {
    setLoading(true);
    try {
      const res = await fetch('/notifications', {
        headers: { Accept: 'application/json' },
      });
      if (res.ok) {
        const data = await res.json();
        setItems(data.slice(0, 10));
      }
    } catch { /* degrade */ }
    setLoading(false);
  };

  useEffect(() => {
    fetchUnread();
    const interval = setInterval(fetchUnread, 60000);
    return () => clearInterval(interval);
  }, []);

  const handleToggle = () => {
    if (!open) {
      loadItems();
    }
    setOpen(!open);
  };

  const handleMarkRead = async (item: NotificationItem) => {
    try {
      await fetch(`/notifications/${item.id}/read`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '' },
      });
      setUnread((c) => Math.max(0, c - 1));
      setItems((prev) => prev.map((n) => (n.id === item.id ? { ...n, read_at: new Date().toISOString() } : n)));
    } catch { /* ignore */ }
    if (item.data?.bookingId) {
      setOpen(false);
      window.location.href = `/bookings/${item.data.bookingId}`;
    }
  };

  const handleMarkAllRead = async () => {
    try {
      await fetch('/notifications/read-all', {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '' },
      });
      setUnread(0);
      setItems((prev) => prev.map((n) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() })));
    } catch { /* ignore */ }
  };

  return (
    <div className="relative">
      <button
        type="button"
        onClick={handleToggle}
        aria-label={`Notifications${unread > 0 ? `, ${unread} unread` : ''}`}
        className="relative flex h-8 w-8 items-center justify-center rounded-full transition-colors hover:bg-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
      >
        {unread > 0 ? (
          <BellRing className="h-5 w-5 text-primary" aria-hidden="true" />
        ) : (
          <Bell className="h-5 w-5 text-textMuted" aria-hidden="true" />
        )}
        {unread > 0 && (
          <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-error px-1 text-[10px] font-bold leading-none text-white">
            {unread > 99 ? '99+' : unread}
          </span>
        )}
      </button>

      {open && (
        <>
          <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
          <div className="absolute right-0 z-50 mt-2 w-80 origin-top-right rounded-interactive bg-surface shadow-raised ring-1 ring-black ring-opacity-5">
            <div className="flex items-center justify-between border-b border-border px-4 py-3">
              <p className="text-sm font-semibold text-text">Notifications</p>
              <div className="flex items-center gap-2">
                {unread > 0 && (
                  <button onClick={handleMarkAllRead} className="text-xs font-medium text-primary hover:underline">
                    Mark all read
                  </button>
                )}
                <button onClick={() => setOpen(false)} className="text-textMuted hover:text-text">
                  ✕
                </button>
              </div>
            </div>

            <div className="max-h-96 overflow-y-auto">
              {loading ? (
                <div className="px-4 py-8 text-center text-sm text-textMuted">Loading...</div>
              ) : items.length === 0 ? (
                <div className="px-4 py-8 text-center text-sm text-textMuted">No notifications</div>
              ) : (
                items.map((item) => (
                  <button
                    key={item.id}
                    onClick={() => handleMarkRead(item)}
                    className={`flex w-full items-start gap-3 border-b border-border px-4 py-3 text-left transition-colors hover:bg-background ${
                      !item.read_at ? 'bg-primary/5' : ''
                    }`}
                  >
                    <span className={`mt-0.5 shrink-0 text-lg ${notificationIconClass(item.type)}`}>
                      {item.type === 'booking_confirmed' ? '✓' :
                       item.type === 'booking_cancelled' ? '✗' :
                       item.type === 'vehicle_checked_out' ? '🚗' :
                       item.type === 'vehicle_returned' ? '🏠' : '🔔'}
                    </span>
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2">
                        <span className={`text-sm ${!item.read_at ? 'font-semibold' : ''} text-text`}>
                          {item.title}
                        </span>
                        {!item.read_at && <span className="h-2 w-2 shrink-0 rounded-full bg-primary" />}
                      </div>
                      <p className="mt-0.5 text-xs text-textMuted line-clamp-2">{item.body}</p>
                      <p className="mt-1 text-[10px] text-textMuted">{formatRelative(item.created_at)}</p>
                    </div>
                  </button>
                ))
              )}
            </div>
          </div>
        </>
      )}
    </div>
  );
}
