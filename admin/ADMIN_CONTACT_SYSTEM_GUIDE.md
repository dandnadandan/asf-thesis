# Admin Contact System Guide

Complete guide for managing the contact system from the admin panel.

## 📋 Overview

The admin contact system consists of two main pages:
1. **View Contact Inquiries** - View and manage form submissions
2. **Manage Contact Information** - Update contact details on the landing page

## 🔗 Access

### Location in Admin Panel
Navigate to: **Contact System** → in the sidebar

### Menu Options
- **View Inquiries** - View all contact form submissions
- **Manage Contact Info** - Update contact information

## 📊 View Contact Inquiries

**URL:** `admin/view_contact_inquiries.php`

### Features

#### Statistics Dashboard
Four gradient cards showing:
- **Total Inquiries** - All time count
- **New Inquiries** - Unread inquiries
- **Read** - Inquiries marked as read
- **Replied** - Inquiries you've responded to

#### Filter & Search
- **Status Filter**: Filter by new, read, replied, or archived
- **Search**: Search across name, email, subject, and message
- **Real-time**: Changes apply immediately

#### Inquiry Cards
Each inquiry displays:
- Subject line (heading)
- Sender name and email
- Submission date and time
- Message content
- IP address
- Current status badge
- Status update dropdown

#### Status Management
Change inquiry status directly:
1. Select new status from dropdown
2. Click "Update" button
3. Status updates immediately with confirmation

### Status Types
- **New** (Blue) - Just submitted, not yet reviewed
- **Read** (Gray) - Viewed by admin
- **Replied** (Green) - Response sent to customer
- **Archived** (Red) - Closed or no longer needed

### Usage Examples

**View New Inquiries:**
1. Select "New" from Status Filter
2. Form submits automatically
3. See only new, unread inquiries

**Search for Specific Inquiry:**
1. Enter search term (name, email, etc.)
2. Click "Search" button
3. Results filter in real-time

**Mark as Replied:**
1. Change status dropdown to "Replied"
2. Click "Update" button
3. Success message confirms change

## ⚙️ Manage Contact Information

**URL:** `admin/manage_contact_info.php`

### Features

#### Live Preview Panel
Left sidebar shows current information:
- Email address
- Phone number
- Full address
- Active social media links
- Visual icons for each field

#### Update Form
Right panel provides form with:
- Email input (required)
- Phone input (required)
- Address textarea (required)
- Facebook URL (optional)
- Twitter URL (optional)
- LinkedIn URL (optional)
- Instagram URL (optional)

### How to Update

**Basic Information:**
1. Enter new email address
2. Enter phone number
3. Update address (can be multi-line)
4. Click "Save Changes"

**Social Media Links:**
1. Paste full URL (including https://)
2. Leave blank to hide that social link
3. Click "Save Changes"
4. Changes appear on landing page immediately

### Important Notes
- ✅ Changes apply **immediately** to landing page
- ✅ Email and phone are **required** fields
- ✅ Social links are **optional**
- ✅ Empty social fields = hidden on landing page
- ✅ URLs must include `https://` or `http://`
- ✅ Preview updates after saving

### Example URLs
```
Facebook:  https://facebook.com/yourpage
Twitter:   https://twitter.com/yourhandle
LinkedIn:  https://linkedin.com/in/yourprofile
Instagram: https://instagram.com/yourhandle
```

## 🔐 Access Control

### Required Roles
Access to contact system requires one of:
- **Administrator**
- **Administrative Staff**
- **Owner**

### Session Management
- Auto-redirects to login if session expires
- Timeout: 30 minutes of inactivity
- Unauthorized users redirected to unauthorized.php

## 🎨 Design Features

### View Inquiries
- Gradient statistic cards (4 colors)
- Card hover effects
- Color-coded status badges
- Smooth transitions
- Responsive layout
- Clean card design with left border accent

### Manage Contact Info
- Purple gradient preview panel
- Glass-morphism effects on preview items
- Clean white form section
- Social media platform icons
- Color-coded social badges
- Info box with important notes

## 💡 Usage Tips

### Best Practices

**For Viewing Inquiries:**
1. Check "New" inquiries daily
2. Mark as "Read" after reviewing
3. Update to "Replied" after responding
4. Archive old/irrelevant inquiries
5. Use search to find specific inquiries

**For Managing Contact Info:**
1. Keep information current and accurate
2. Test social links before saving
3. Use consistent formatting for address
4. Include country code in phone number
5. Verify changes on landing page after saving

### Workflow Suggestion

1. **Morning Routine:**
   - Check new inquiries
   - Respond to urgent ones
   - Update statuses

2. **Weekly Task:**
   - Review all "Read" inquiries
   - Archive old inquiries
   - Update contact info if needed

3. **Monthly Review:**
   - Check inquiry statistics
   - Analyze common questions
   - Update FAQ if patterns emerge

## 📊 Database Tables

### contact_inquiries
Stores all form submissions:
- `id` - Unique identifier
- `name` - Sender name
- `email` - Sender email
- `subject` - Message subject
- `message` - Full message
- `status` - new/read/replied/archived
- `ip_address` - Sender IP
- `user_agent` - Browser info
- `created_at` - Submission time
- `updated_at` - Last modified

### contact_information
Stores displayed contact details:
- `id` - Unique identifier
- `email` - Contact email
- `phone` - Contact phone
- `address` - Business address
- `facebook_url` - Facebook page
- `twitter_url` - Twitter profile
- `linkedin_url` - LinkedIn profile
- `instagram_url` - Instagram profile
- `is_active` - Active flag
- `created_at` - Creation time
- `updated_at` - Last modified

### contact_inquiry_statistics (View)
Provides real-time statistics:
- `total_inquiries` - All inquiries
- `new_inquiries` - Unread count
- `read_inquiries` - Read count
- `replied_inquiries` - Replied count
- `archived_inquiries` - Archived count
- `last_inquiry_date` - Most recent

## 🔧 Troubleshooting

### Issue: Statistics not showing
**Solution:** Check if contact_inquiry_statistics view exists
```sql
SELECT * FROM contact_inquiry_statistics;
```

### Issue: Can't update contact info
**Solution:** Verify contact_information table exists and has is_active = 1 record

### Issue: Changes not appearing on landing page
**Solution:** 
1. Clear browser cache
2. Verify API endpoint is accessible
3. Check database connection

### Issue: Unauthorized access error
**Solution:** 
1. Check user role (must be admin/owner)
2. Verify session is active
3. Re-login if necessary

## 📱 Responsive Design

Both pages are fully responsive:
- **Desktop**: Full layout with sidebars
- **Tablet**: Adjusted columns and spacing
- **Mobile**: Stacked layout, optimized for touch

## 🚀 Quick Reference

### View Inquiries Quick Actions
```
Filter by New:     Status Filter → New
Search by Email:   Search box → user@example.com → Search
Mark as Replied:   Dropdown → Replied → Update
Archive Old:       Dropdown → Archived → Update
```

### Manage Contact Quick Actions
```
Update Email:      Email field → new@example.com → Save
Update Phone:      Phone field → 09123456789 → Save
Add Social:        Facebook URL → https://... → Save
Remove Social:     Clear URL field → Save
```

## 📞 Support

For issues or questions:
1. Check this documentation
2. Review main documentation: `CONTACT_SYSTEM_SETUP.md`
3. Check browser console for errors
4. Verify database tables exist
5. Test API endpoints

## 🔄 Integration

### With Main System
- Uses admin includes (head, header, sidebar, footer)
- Follows admin authentication pattern
- Matches admin design system
- Uses same database configuration
- Consistent with other admin pages

### With Landing Page
- Contact info syncs with index.php
- Real-time updates via API
- Inquiries come from public form
- Seamless integration

---

**Created:** October 2025  
**Version:** 1.0  
**System:** TaxEase Admin Panel  
**Module:** Contact System Management

