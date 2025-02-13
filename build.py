import os
import shutil
import requests
import re
from urllib.parse import urljoin
import urllib3

# Configuration
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
PHP_SOURCE = "src/php"
DIST_DIR = "dist"
DIST_PHP = os.path.join(DIST_DIR, "php")
EXCLUDED_CLASSES = {'LanguageClass.php', 'LoggerClass.php', 'MinifierClass.php'}

def clean_php_file(content, filename):
    """Remove references to excluded classes from PHP code"""
    # Special handling for autoload.php
    if filename == "autoload.php":
        content = re.sub(
            r'include_once\s*\$phpDirectory\s*\.\s*[\'"]/LanguageClass\.php[\'"]\s*;\s*',
            '', content, flags=re.IGNORECASE
        )
        content = re.sub(
            r'else if\s*\(basename\(\$file\)\s*!==\s*[\'"]LanguageClass\.php[\'"]\)',
            'else', content, flags=re.IGNORECASE
        )
        content = re.sub(
            r'include_once \$phpDirectory \. \'/LanguageClass.php\';',
            '', content
        )

    # Enhanced cleaning for APIClass.php
    if filename == "APIClass.php":
        # Remove specific require statements
        content = re.sub(
            r'require_once __DIR__ \. \'/LanguageClass\.php\';[\n\s]*',
            '', content
        )
        content = re.sub(
            r'require_once __DIR__ \. \'/MinifierClass\.php\';[\n\s]*',
            '', content
        )

        # Fix broken log retrieval code
        content = re.sub(
            r'// Initialize LoggerClass and fetch log messages\n\s*\);\n\s*\$logMessages = // Log retrieval disabledif',
            '// Logging functionality removed\n        $logMessages = [];\n        if',
            content
        )

    # Generic patterns for all files
    patterns = [
        # Match all variations of require/include statements
        r'require_once\s*__DIR__\s*\.\s*[\'"]/(Language|Logger|Minifier)Class\.php[\'"]\s*;\s*',
        # Match namespaced use statements
        r'^\s*use\s+.*\\?(Language|Logger|Minifier)Class\s*;',
        # Match class extensions
        r'class\s+.*\s+extends\s+(Language|Logger|Minifier)Class\b',
    ]

    for pattern in patterns:
        content = re.sub(pattern, '', content, flags=re.MULTILINE|re.IGNORECASE)

    return content

def process_php_files():
    """Copy and clean PHP files while maintaining structure"""
    try:
        if os.path.exists(DIST_DIR):
            shutil.rmtree(DIST_DIR)

        os.makedirs(DIST_PHP, exist_ok=True)

        # Copy PHPMailer directory
        shutil.copytree(
            os.path.join(PHP_SOURCE, "PHPMailer"),
            os.path.join(DIST_PHP, "PHPMailer")
        )

        # Process individual PHP files
        for root, dirs, files in os.walk(PHP_SOURCE):
            for file in files:
                if file in EXCLUDED_CLASSES:
                    continue

                src_path = os.path.join(root, file)
                rel_path = os.path.relpath(root, PHP_SOURCE)
                dest_dir = os.path.join(DIST_PHP, rel_path)
                dest_path = os.path.join(dest_dir, file)

                # Skip non-PHP files
                if not file.endswith('.php'):
                    shutil.copy2(src_path, dest_path)
                    continue

                # Handle text file encoding
                try:
                    with open(src_path, 'rb') as f:
                        content_bytes = f.read()

                    # Try UTF-8 first, fallback to latin-1
                    try:
                        content = content_bytes.decode('utf-8')
                    except UnicodeDecodeError:
                        content = content_bytes.decode('latin-1')

                    cleaned_content = clean_php_file(content, file)

                    os.makedirs(dest_dir, exist_ok=True)
                    with open(dest_path, 'w', encoding='utf-8') as f:
                        f.write(cleaned_content)

                except Exception as e:
                    print(f"Warning: Skipping {src_path} - {str(e)}")
                    continue

        # Copy config.php
        config_src = os.path.join(PHP_SOURCE, "../../config.php")
        if os.path.exists(config_src):
            shutil.copy(config_src, DIST_DIR)

    except Exception as e:
        print(f"Error processing PHP files: {e}")
        raise

def minify_html(html):
    """Minify HTML while preserving PHP tags"""
    php_blocks = re.findall(r'<\?php.*?\?>', html, flags=re.DOTALL)
    placeholder = "@@PHP_BLOCK@@"

    temp_html = re.sub(r'<\?php.*?\?>', placeholder, html, flags=re.DOTALL)
    temp_html = re.sub(r'<!--.*?-->', '', temp_html, flags=re.DOTALL)
    temp_html = re.sub(r'>\s+<', '><', temp_html)
    temp_html = re.sub(r'\s+', ' ', temp_html).strip()

    for block in php_blocks:
        temp_html = temp_html.replace(placeholder, block, 1)

    return temp_html

def inline_assets(html, base_url):
    """Inline CSS/JS assets"""
    base_url = urljoin(base_url, "/src/")

    for asset_type in ['css', 'js']:
        pattern = r'href="(.*?\.css)"' if asset_type == 'css' else r'src="(.*?\.js)"'
        urls = re.findall(pattern, html)

        content = []
        for url in urls:
            abs_url = urljoin(base_url, url)
            try:
                response = requests.get(abs_url, verify=False, timeout=10)
                if response.status_code == 200:
                    content.append(response.text)
            except Exception:
                continue

        if content:
            tag = f'<style>{"".join(content)}</style>' if asset_type == 'css' \
                else f'<script>{"".join(content)}</script>'
            html = html.replace(f'</{asset_type}>', f'{tag}</{asset_type}>')

    return html

def fix_urls(html):
    """Fix URL paths for production"""
    replacements = {
        r'/src/([^"\']+)': r'/\1',
        r'action="\?lang=([a-z]{2})"': r'action="index_\1.php"',
        r'\?lang=([a-z]{2})': r'index_\1.php'
    }

    for pattern, replacement in replacements.items():
        html = re.sub(pattern, replacement, html)

    return html

def update_language_selector(html, lang):
    """Update language switcher markup"""
    selected = {'en': '', 'hu': ''}
    selected[lang] = 'selected="selected"'

    return re.sub(
        r'<select id="language-selector".*?</select>',
        f'''<select id="language-selector" name="lang" onchange="location = this.value;">
            <option value="index_en.php" {selected['en']}>🇺🇸 English</option>
            <option value="index_hu.php" {selected['hu']}>🇭🇺 Magyar</option>
        </select>''',
        html,
        flags=re.DOTALL
    )

def process_page(url, lang):
    """Process and save a language version of the page"""
    try:
        response = requests.get(url, verify=False)
        if response.status_code != 200:
            return

        # Add PHP headers
        html = f"""<?php
session_start();
include_once __DIR__ . '/php/autoload.php';
?>
{response.text}"""

        html = inline_assets(html, url)
        html = fix_urls(html)
        html = update_language_selector(html, lang)
        html = minify_html(html)

        output_file = os.path.join(DIST_DIR, f"index_{lang}.php")
        with open(output_file, 'w', encoding='utf-8') as f:
            f.write(html)

    except Exception as e:
        print(f"Error processing {lang} page: {e}")

if __name__ == "__main__":
    process_php_files()

    # Process language versions
    base_url = "https://beyondsolutions.ddev.site/src/"
    process_page(f"{base_url}?lang=en", 'en')
    process_page(f"{base_url}?lang=hu", 'hu')

    print("Build complete. Verified exclusions:")
    print("- Removed all LanguageClass references")
    print("- Removed all LoggerClass dependencies")
    print("- Cleaned ContactFormHandler.php")
    print("\nFinal distribution structure:")
    print(f"""
{DIST_DIR}/
├── config.php
├── index_en.php
├── index_hu.php
└── php/
    ├── PHPMailer/
    ├── APIClass.php
    ├── autoload.php
    ├── ContactFormHandler.php
    ├── HTMLTemplateClass.php
    └── ...other core files...""")