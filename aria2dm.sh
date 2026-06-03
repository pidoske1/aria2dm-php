#!/bin/bash

# Warna untuk output terminal
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}      Aria2DM-PHP Auto Installer         ${NC}"
echo -e "${GREEN}=========================================${NC}"

# Cek apakah dijalankan sebagai root
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}[ERROR] Script ini harus dijalankan sebagai root! Gunakan perintah: sudo ./aria2dm.sh${NC}"
  exit 1
fi

# Cek apakah folder sumber ada
if [ ! -d "aria2dm" ]; then
  echo -e "${RED}[ERROR] Folder 'aria2dm' tidak ditemukan! Pastikan Anda menjalankan script ini dari folder hasil ekstrak repositori.${NC}"
  exit 1
fi

# Tentukan lokasi web server
DEST_DIR="/var/www/html/aria2dm"

echo -e "\n${YELLOW}[1/4] Menginstal dependensi yang dibutuhkan (aria2, php, php-curl)...${NC}"
apt-get update
apt-get install -y aria2 php php-curl curl

echo -e "\n${YELLOW}[2/4] Membuat direktori web dan menyalin file...${NC}"
mkdir -p $DEST_DIR
cp -r aria2dm/* $DEST_DIR/

echo -e "\n${YELLOW}[3/4] Mengatur perizinan (Permissions)...${NC}"
chown -R www-data:www-data $DEST_DIR
chmod -R 775 $DEST_DIR

echo -e "\n${YELLOW}[4/4] Konfigurasi Selesai!${NC}"
echo -e "${GREEN}=========================================${NC}"
echo -e "Instalasi berhasil dilakukan!"
echo -e "1. Buka browser dan akses: ${YELLOW}http://<IP-SERVER-ANDA>/aria2dm${NC}"
echo -e "2. Password default adalah: ${YELLOW}admin123${NC}"
echo -e "3. Setelah login, buka menu Settings lalu klik Save untuk inisialisasi awal."
echo -e "${GREEN}=========================================${NC}"