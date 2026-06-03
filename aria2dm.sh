#!/bin/bash

# Warna untuk output terminal
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' 

echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}      Aria2DM-PHP Auto Installer         ${NC}"
echo -e "${GREEN}=========================================${NC}"

# 1. Cek Root
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}[ERROR] Script ini harus dijalankan sebagai root! Gunakan: sudo bash aria2dm.sh${NC}"
  exit 1
fi

# 2. Perbaikan Line Endings (Mencegah error 'No such file or directory')
# Script ini akan membersihkan karakter Windows jika ada
sed -i 's/\r$//' aria2dm/*.php 2>/dev/null

# 3. Cek apakah folder sumber ada
if [ ! -d "aria2dm" ]; then
  echo -e "${RED}[ERROR] Folder 'aria2dm' tidak ditemukan! Pastikan Anda berada di folder yang sama dengan folder aria2dm.${NC}"
  exit 1
fi

DEST_DIR="/var/www/html/aria2dm"

echo -e "\n${YELLOW}[1/4] Menginstal dependensi...${NC}"
apt-get update
apt-get install -y aria2 php php-curl curl

echo -e "\n${YELLOW}[2/4] Menyalin file ke $DEST_DIR...${NC}"
mkdir -p $DEST_DIR
cp -r aria2dm/* $DEST_DIR/

echo -e "\n${YELLOW}[3/4] Mengatur perizinan web server...${NC}"
chown -R www-data:www-data $DEST_DIR
find $DEST_DIR -type d -exec chmod 755 {} \;
find $DEST_DIR -type f -exec chmod 644 {} \;

echo -e "\n${YELLOW}[4/4] Selesai!${NC}"
echo -e "${GREEN}=========================================${NC}"
echo -e "Instalasi sukses!"
echo -e "1. Akses: ${YELLOW}http://$(hostname -I | awk '{print $1}')/aria2dm${NC}"
echo -e "2. Password: ${YELLOW}admin123${NC}"
echo -e "3. Penting: Jika muncul error permission di dashboard, klik tombol 'Save' di menu Settings untuk memunculkan script perbaikan otomatis."
echo -e "${GREEN}=========================================${NC}"
