# Aria2DM-PHP (Aria2 Download Manager)

A lightweight, sleek, and responsive web-based download manager for Aria2, built with PHP and Tailwind CSS. 

Aria2DM-PHP allows you to control your Aria2c engine directly from your browser with a modern Dark Mode UI, smart auto-resume capabilities, and multi-language support.

## ✨ Features
* **Modern UI:** Dark mode by default, built with Tailwind CSS.
* **Smart Engine Management:** Start, stop, and configure the Aria2 engine straight from the dashboard.
* **Smart Resume & Edit:** Intelligently handles resumed downloads and URL edits even after a server reboot (memory flush).
* **Bulk Actions:** Resume, pause, or delete multiple tasks at once.
* **Speed Control:** Set global speed limits or adjust limits for individual files.
* **Multi-language:** Currently supports English and Indonesian.
* **Category Filtering:** Auto-detects file types and sorts them into categories (Videos, Compressed, Documents, Music, etc.).

## 🚀 Requirements
* Linux OS (Ubuntu/Debian/Armbian recommended)
* Web Server (Apache / Nginx)
* PHP 7.4 or newer (with `php-curl` extension)
* `aria2` package

## 📦 Installation

**Method 1: Using the Installer Script (Recommended)**
1. Clone this repository or download the ZIP file and extract it.
2. Navigate to the extracted folder.
3. Run the installer script as root/sudo:
   chmod +x aria2dm.sh
   sudo ./aria2dm.sh
4. Access the dashboard via your browser: http://your-server-ip/aria2dm
5. Default Password: admin (request a change after first login)
