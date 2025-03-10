# WordPress CDN Integration

Integrate WordPress with GitHub and jsDelivr CDN for serving static files faster and more efficiently.

## Description

WordPress CDN Integration allows you to serve your static assets (CSS, JavaScript, images, etc.) through the jsDelivr CDN by leveraging GitHub as a storage repository. This can significantly improve your website's loading speed and reduce server load.

### Key Features

- Automatically serve static files through jsDelivr CDN
- Upload static assets to GitHub with just a few clicks
- Analyze your site to find static assets that can be served via CDN
- Validate and auto-upload custom URLs
- Easily purge the CDN cache when needed
- Debug mode with detailed logging

### How It Works

1. The plugin uploads your static assets to a GitHub repository
2. Static files are then served from jsDelivr CDN instead of your server
3. This reduces server load and improves global content delivery speed

## Installation

1. Upload the `wp-cdn-integration` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'CDN Integration' in the admin menu to configure settings

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- GitHub account with a repository for storing static assets
- GitHub personal access token with repository write access

## Configuration

### GitHub Settings

1. Create a GitHub repository to store your static files
2. Generate a personal access token with 'repo' scope at GitHub Settings > Developer settings > Personal access tokens
3. Enter your GitHub username, repository name, and personal access token in the plugin settings

### CDN Settings

1. Select which file types you want to serve through the CDN
2. Optionally exclude specific paths from being served via CDN
3. Add custom URLs that should be served through the CDN

## Usage

### Analyzing Your Site

Use the URL Analyzer to discover static assets on your site:

1. Quick Analysis - Scans your homepage for static assets
2. Deep Analysis - Crawls multiple pages to find all static assets
3. Manual Entry - Paste URLs directly for analysis

### Uploading to GitHub

After finding static assets, you can upload them to GitHub directly from the plugin interface:

1. Select the files you want to upload
2. Click "Upload to GitHub"
3. The plugin will automatically upload the files to your GitHub repository

### Purging the CDN Cache

After updating static files, you may want to purge the jsDelivr CDN cache:

1. Go to the plugin settings
2. Click the "Purge Cache" button to refresh the CDN cache

## Troubleshooting

### View Logs

The plugin includes a log viewer to help you troubleshoot any issues:

1. Go to "CDN Integration > View Log" in the admin menu
2. Review the log entries for any errors or issues
3. Enable debug mode in settings for more detailed logs

## FAQ

**Q: Is this plugin free to use?**  
A: Yes, the plugin is completely free. You'll need a free GitHub account and repository to store your static files, and jsDelivr CDN is also free to use.

**Q: Will this plugin affect my site's appearance?**  
A: No, the plugin only changes where your static files are served from, not how they look or function.

**Q: Can I use a private GitHub repository?**  
A: No, jsDelivr requires public repositories to serve files. You should not upload sensitive information.

**Q: How much can this improve my site's performance?**  
A: Performance improvements vary based on your hosting, visitor locations, and current setup. Generally, you can expect faster load times, especially for visitors located far from your hosting server.

**Q: How do I update files when I make changes?**  
A: When you update files on your site, use the Analyzer again to find the updated files, then upload them to GitHub. Use the "Purge Cache" button to refresh the CDN.

## Support and Contributions

For support requests or to contribute to the plugin, please visit the [GitHub repository](https://github.com/magoarab/wordpress-cdn-integration).

## License

This plugin is licensed under the GPL v2 or later.