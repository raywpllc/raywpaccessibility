# RayWP Accessibility Plugin - Build Instructions

## Creating a Release ZIP

To create a clean zip file for WordPress plugin distribution:

### Quick Start
```bash
./create-release-zip.sh
```

The script will automatically:
- ✅ Extract version number from the main plugin file
- ✅ Create a clean copy excluding development files
- ✅ Generate `raywp-accessibility-v{VERSION}.zip` on your Desktop
- ✅ Verify file size is under 10MB
- ✅ Clean up temporary files

### What Gets Excluded
- `.git/` directory and Git files
- `CLAUDE.md` (AI development notes)
- `tasks/` directory (development tasks)
- `test-form.html` (development test file)
- `.claude/` directory (Claude Code IDE settings)
- Symbolic links
- Log files, temporary files, and system files
- IDE-specific directories (`.vscode/`, `.idea/`)

### What Gets Included
- All PHP plugin files
- CSS and JavaScript assets
- Images and other media
- `readme.txt` for WordPress.org
- Language files directory (for future translations)
- Template files directory

### Requirements
- macOS or Linux with `rsync` and `zip` commands
- Bash shell
- Write permissions to Desktop

### Output
- **File**: `~/Desktop/raywp-accessibility-v{VERSION}.zip`
- **Size**: Typically ~88KB (well under 10MB limit)
- **Ready for**: WordPress plugin installation or submission

---
*Note: The script is excluded from the zip file itself to keep the distribution clean.*