# TaxEase API Endpoints

This directory contains API endpoints for the TaxEase system.

## Contact System Endpoints

### 1. Submit Contact Inquiry
**File:** `submit_contact_inquiry.php`  
**Method:** POST  
**Purpose:** Handle contact form submissions from the landing page

**Request:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "subject": "Question about services",
  "message": "I would like to know more..."
}
```

**Response:**
```json
{
  "success": true,
  "message": "Thank you for contacting us!",
  "inquiry_id": 1
}
```

### 2. Get Contact Information
**File:** `get_contact_info.php`  
**Method:** GET  
**Purpose:** Retrieve contact information for display on the landing page

**Response:**
```json
{
  "success": true,
  "data": {
    "email": "fkeepers2013@gmail.com",
    "phone": "09178852769",
    "address": "...",
    "facebook_url": "...",
    "twitter_url": null,
    "linkedin_url": null,
    "instagram_url": null
  }
}
```

## Security Features

- SQL Injection Prevention (PDO prepared statements)
- XSS Prevention (output escaping)
- Email Validation
- Input Sanitization
- Length Validation
- CORS Headers
- HTTP Method Validation

## Testing

Test the endpoints using the test page:
```
http://localhost/tax_ease/test_contact_system.php
```

## Documentation

For complete documentation, see:
- `CONTACT_SYSTEM_SETUP.md`
- `CONTACT_SYSTEM_QUICK_START.md`
- `DYNAMIC_CONTACT_COMPLETE_SUMMARY.md`

