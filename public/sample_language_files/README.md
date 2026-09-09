# Language Sample Files

This directory contains sample language files for different platforms in the eDemand system.

## File Structure

```
samples/
├── sample_panel.json      # Admin Panel sample file
├── sample_web.json        # Web Frontend sample file
├── sample_customer.json   # Customer Mobile App sample file
├── sample_provider.json   # Provider Mobile App sample file
├── default_sample.json    # Default fallback sample file
└── README.md             # This documentation file
```

## Platform-Specific Sample Files

### 1. Panel Sample (`sample_panel.json`)

- **Purpose**: Admin panel language keys
- **Target Platform**: Admin Dashboard
- **Key Features**:
  - Administrative interface terms
  - System management vocabulary
  - User management language
  - Settings and configuration terms

### 2. Web Sample (`sample_web.json`)

- **Purpose**: Web frontend language keys
- **Target Platform**: Public website
- **Key Features**:
  - Customer-facing interface terms
  - Service browsing language
  - Booking and payment terms
  - User account management

### 3. Customer App Sample (`sample_customer.json`)

- **Purpose**: Customer mobile app language keys
- **Target Platform**: Customer mobile application
- **Key Features**:
  - Mobile app specific terms
  - Booking and service management
  - Payment and transaction language
  - User profile and preferences

### 4. Provider App Sample (`sample_provider.json`)

- **Purpose**: Provider mobile app language keys
- **Target Platform**: Service provider mobile application
- **Key Features**:
  - Provider dashboard terms
  - Service management language
  - Booking and scheduling terms
  - Earnings and analytics

### 5. Default Sample (`default_sample.json`)

- **Purpose**: Fallback sample file
- **Usage**: Used when platform-specific samples are not available
- **Key Features**:
  - Common language keys across all platforms
  - Basic navigation and UI terms
  - Universal action words

## Usage

### Downloading Sample Files

1. Navigate to the Languages page in the admin panel
2. In the "Add Language" section, find the "Sample Files" area
3. Click the download button for your desired platform:
   - **Panel**: Admin panel sample
   - **Web**: Web frontend sample
   - **Customer App**: Customer mobile app sample
   - **Provider App**: Provider mobile app sample

### File Format

All sample files are in JSON format with the following structure:

```json
{
  "key_name": "Translated Text",
  "another_key": "Another Translation"
}
```

### Customization

1. Download the appropriate sample file for your platform
2. Modify the translations as needed
3. Upload the modified file when creating a new language
4. Ensure all required keys are present for your platform

## Technical Details

### File Naming Convention

- Format: `sample_{platform}.json`
- Platforms: `panel`, `web`, `customer`, `provider`
- Fallback: `default_sample.json`

### Download URLs

- Panel: `/download_sample_file/panel`
- Web: `/download_sample_file/web`
- Customer App: `/download_sample_file/customer`
- Provider App: `/download_sample_file/provider`

### Error Handling

- If a platform-specific sample is not found, the system falls back to `default_sample.json`
- Invalid categories show an error message
- Download failures are handled gracefully with user feedback

## Best Practices

1. **Keep Keys Consistent**: Use the same key names across all platforms when possible
2. **Platform-Specific Terms**: Add platform-specific keys only where necessary
3. **Regular Updates**: Update sample files when new features are added
4. **Validation**: Always validate JSON syntax before uploading
5. **Backup**: Keep backups of your language files

## Support

For issues with sample files or language functionality:

1. Check the file format and JSON syntax
2. Verify the file contains all required keys for your platform
3. Ensure the file is properly encoded (UTF-8 recommended)
4. Contact the development team for technical support

## Version History

- **v1.0**: Initial release with basic sample files
- **v1.1**: Added platform-specific samples
- **v1.2**: Enhanced download functionality with user feedback
- **v1.3**: Added fallback mechanism and improved error handling
