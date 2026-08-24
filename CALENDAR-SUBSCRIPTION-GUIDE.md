# Calendar Subscription Guide

## Overview

Allow customers to subscribe to bookings and events via iCal feeds that work with:
- Google Calendar
- Apple Calendar (iCal)
- Microsoft Outlook
- Any iCal-compatible app

## Feed URLs

### All Bookings
- iCal: `https://yoursite.com/feed/sbe-bookings.ics`
- Google: `https://calendar.google.com/calendar/render?cid=https://yoursite.com/feed/sbe-bookings.ics`
- Apple/Outlook: `webcal://yoursite.com/feed/sbe-bookings.ics`

### All Events
- iCal: `https://yoursite.com/feed/sbe-events.ics`

### User Specific
- iCal: `https://yoursite.com/feed/sbe-user-{USER_ID}.ics`

### Single Booking
- iCal: `https://yoursite.com/feed/sbe-booking-{BOOKING_ID}.ics`

## Configuration

### Enable Calendar Feeds
1. Go to Settings → Calendar Subscription
2. Check "Enable Calendar Feeds"
3. Select timezone
4. Choose what to include (bookings, events, or both)
5. Set event title format
6. Save settings

### Event Title Format

Available placeholders:
- `{service}` - Service name
- `{event}` - Event name
- `{customer}` - Customer name
- `{date}` - Booking date
- `{time}` - Booking time

Examples:
```
{service} - {customer}
→ "Haircut - John Doe"

{date} at {time}: {service}
→ "August 25, 2026 at 10:00 AM: Haircut"
```

## Shortcodes

### Calendar Subscribe
```
[sbe_calendar_subscribe type="all"]
```

Attributes:
- `type`: all, bookings, events, user
- `user_id`: Specific user ID (for type="user")

### Add to Calendar Button
```
[sbe_add_to_calendar booking_id="123"]
```

Attributes:
- `booking_id`: Specific booking ID
- `event_id`: Specific event ID
- `style`: button or link

## Customer Usage

### Google Calendar
1. Click "Google Calendar" button
2. Calendar opens in new tab
3. Click "Yes" to add
4. Events appear automatically

### Apple Calendar
1. Click "Apple/Outlook" button
2. Calendar app opens
3. Click "Subscribe"
4. Events sync automatically

### Outlook
1. Click "Apple/Outlook" button
2. Outlook opens
3. Click "Yes" to subscribe
4. Calendar appears in Outlook

### Manual Subscribe
1. Open calendar app
2. Find "Subscribe to calendar" or "Add calendar"
3. Enter feed URL
4. Confirm subscription
5. Set refresh interval (recommended: daily)

## Email Integration

Calendar links automatically added to:
- Booking confirmation emails
- Event registration emails
- Payment confirmations

Three buttons in each email:
1. Download .ics
2. Google Calendar
3. Apple/Outlook

## Troubleshooting

### Feed not downloading
1. Flush rewrite rules (Settings → Permalinks → Save)
2. Check URL format
3. Verify SSL configured

### Events not appearing
1. Check date range (only future events)
2. Verify booking status is "confirmed"
3. Check "Include bookings" is enabled

### Timezone issues
1. Set correct timezone in settings
2. Verify WordPress timezone matches
3. Check event times are correct

### Google Calendar not updating
1. Force refresh in Google Calendar settings
2. Wait for automatic refresh (up to 24 hours)
3. Re-subscribe if needed

### iCal file invalid
1. Verify file starts with `BEGIN:VCALENDAR`
2. Check UTF-8 encoding
3. Use online iCal validator

## Best Practices

### Feed Management
- Limit historical data (last 30 days)
- Include events up to 1 year in future
- Only show confirmed bookings
- Don't expose sensitive information

### Customer Experience
- Provide clear subscription instructions
- Offer multiple calendar options
- Include links in all booking emails
- Show subscribe buttons on confirmation page

### Technical
- Always use HTTPS
- Set appropriate cache headers
- Recommend daily refresh to customers
- Monitor feed generation performance

## Security

### Feed Privacy
- Feeds are publicly accessible by default
- Don't include sensitive information
- Use hashed user IDs for privacy

### Rate Limiting
- Prevent abuse with rate limiting
- Monitor feed access patterns

### Access Control
For private feeds:
- Require authentication
- Use unique tokens per user
- Implement expiration

## Support

- iCal Specification: [RFC 5545](https://tools.ietf.org/html/rfc5545)
- Google Calendar: [support.google.com/calendar](https://support.google.com/calendar)
- Apple Calendar: [support.apple.com](https://support.apple.com)
