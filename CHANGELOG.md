# Changelog

All notable changes to the Form Settings plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.3] - 2026-02-23

### Added
- Frontend min/max length validation: submit button disabled when field length constraints are not met
- Hover tooltip now shows specific messages for each violation (e.g. "Full Name must be at least 5 characters (currently 2)")
- Min/max length rules passed to frontend via wp_localize_script unified rules array

### Fixed
- Error log was showing "unknown: Invalid" for validation errors instead of actual field names
- CF7 `invalid_fields` CSS selector now parsed to extract the real field name
- Replaced non-existent `get_invalid_fields()` on contact form object with `WPCF7_Submission::get_instance()->get_invalid_fields()`

## [1.0.2] - 2026-02-22

### Added
- Frontend validation: submit button is disabled on page load when required fields (configured in the Validation tab) are present
- Submit button enables only when all required fields are filled
- Submit button returns to disabled state after a successful CF7 form reset

### Changed
- Refactored `class-frontend-scripts.php` to use `wp_localize_script` for passing required field data to JS
- Combined copy-paste control and required-field logic into a single enqueue function

## [1.0.1] - 2026-02-15

### Fixed
- **Critical**: Fixed Contact Form 7 forms getting stuck in "submitting" state when plugin is active
- Added output buffering to error logging methods to prevent corrupting CF7's AJAX JSON response
- Fixed "Undefined array key 'tag_name'" warning in plugin updater
- Fixed "Array to string conversion" warning in contact-us.php template
- Added comprehensive error handling with try-catch blocks for Exception and Throwable
- Enhanced `get_submission_data()` with additional validation checks

### Changed
- Error logging now fails silently and logs to PHP error log instead of blocking form submissions
- Improved validation checks before accessing array keys in error logger
- Added class existence check for WPCF7_Submission before using it

## [1.0.0] - 2026-01-20

### Added
- Recipients management system for Contact Form 7
- Automatic form scanning to detect email fields
- Validation rules manager for form fields
- Email template system with custom templates
- Error logging system with detailed tracking
- Error log viewer with statistics and filtering
- Copy/paste control settings for forms
- JS conflict scanner to detect jQuery Validate issues
- Settings page with toggle controls
- Admin interface with tabbed navigation

### Features
- **Recipients Tab**: Add, edit, and manage email recipients per form
- **Validation Rules Tab**: Set min/max length and required field rules
- **Form Scanner Tab**: Automatically scan forms for email fields
- **Email Templates Tab**: Create reusable email templates
- **Error Logs**: View and filter form submission errors
- **Settings Tab**: Global plugin settings and JS conflict detection

### Technical
- WordPress 5.0+ compatibility
- Contact Form 7 integration
- AJAX-powered admin interface
- Secure nonce verification
- Sanitized data handling
- Error logging with IP tracking
- Keeps last 500 error entries

## [Unreleased]

### Planned
- Export/import settings functionality
- Email notification for errors
- Advanced filtering in error logs
- Form analytics dashboard
