# GitHub Setup Instructions

## Repository Initialized ✅

Your project has been initialized as a Git repository and is ready to be pushed to GitHub.

## Next Steps to Push to GitHub

### 1. Create a GitHub Repository

1. Go to https://github.com and log in
2. Click the "+" icon in the top right corner
3. Select "New repository"
4. Name your repository (e.g., "onsea-news" or "busken")
5. **DO NOT** initialize with README, .gitignore, or license (we already have these)
6. Click "Create repository"

### 2. Connect Your Local Repository to GitHub

After creating the repository, GitHub will show you commands. Use these commands in your terminal:

```bash
cd C:\Users\gavin\OneDrive\Documents\busken

# Add the remote repository (replace YOUR_USERNAME and YOUR_REPO_NAME)
git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git

# Rename the default branch to main (if needed)
git branch -M main

# Push your code to GitHub
git push -u origin main
```

### 3. Alternative: Using SSH (if you have SSH keys set up)

```bash
git remote add origin git@github.com:YOUR_USERNAME/YOUR_REPO_NAME.git
git branch -M main
git push -u origin main
```

## Important Notes

### Sensitive Files Excluded

The following sensitive files are excluded from Git (via `.gitignore`):
- `config/database.php` - Contains database credentials
- `config/geocoding.php` - Contains API keys
- `config/base-config.php` - May contain sensitive config

**IMPORTANT**: These files will NOT be pushed to GitHub. You'll need to:
1. Create these files manually on your server
2. Or use environment variables
3. Or create template files (e.g., `config/database.php.example`)

### Creating Template Config Files (Recommended)

You may want to create example/template files that can be committed:

```bash
# Create template files
cp config/database.php config/database.php.example
cp config/geocoding.php config/geocoding.php.example
cp config/base-config.php config/base-config.php.example

# Edit the .example files to remove sensitive data and add placeholder values
# Then commit them:
git add config/*.example
git commit -m "Add config file templates"
git push
```

## Future Updates

After making changes to your code:

```bash
# Stage your changes
git add .

# Commit with a descriptive message
git commit -m "Description of your changes"

# Push to GitHub
git push
```

## Troubleshooting

### If you get authentication errors:
- Use a Personal Access Token instead of password
- Or set up SSH keys for GitHub
- See: https://docs.github.com/en/authentication

### If you need to update the remote URL:
```bash
git remote set-url origin https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git
```

### To check your remote:
```bash
git remote -v
```


