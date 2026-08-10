/**
 * Service worker for the Car Rental storefront.
 *
 * Handles web push (VAPID) notifications:
 *   - `push` event            → deserialize the payload and show a notification.
 *                               The payload is the JSON the backend's
 *                               PushNotificationService sends, or an empty
 *                               fallback so a push with no body still works.
 *   - `notificationclick`     → close the notification and open the booking
 *                               detail page when the payload carries a
 *                               bookingId; otherwise the homepage.
 *
 * Registered from resources/js/pushNotifications.ts on every page load
 * (degrading silently when Push is unsupported — see that module).
 */

self.addEventListener('push', (event) => {
  const data = event.data?.json() ?? {};
  const title = data.title || 'Car Rental';
  const options = {
    body: data.body || '',
    icon: '/favicon.ico',
    badge: '/favicon.ico',
    data: data.data || {},
    requireInteraction: true,
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const { bookingId, type } = event.notification.data;
  let url = '/';
  if (bookingId) {
    url = `/bookings/${bookingId}`;
  }
  event.waitUntil(clients.openWindow(url));
});
