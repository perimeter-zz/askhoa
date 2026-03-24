#!/usr/bin/env python3
"""Deploy local files to Hostinger via SFTP."""

import os
import sys
import paramiko

SSH_HOST = "212.85.29.178"
SSH_PORT = 65002
SSH_USER = "u789657666"
SSH_PASS = "#Askhoa0"
REMOTE_PATH = "/home/u789657666/domains/askhoa.org/public_html"
LOCAL_PATH = os.path.dirname(os.path.abspath(__file__))

# Files/dirs to exclude from deployment
EXCLUDE = {
    ".git", ".gitignore", ".claude", "deploy_from_pc.py", "test_agent.py",
    "storage_cleanup.sh", "__pycache__", ".env", "*.pyc"
}

def should_exclude(name):
    if name in EXCLUDE:
        return True
    if any(name.endswith(ext.lstrip("*")) for ext in EXCLUDE if ext.startswith("*")):
        return True
    return False

def upload_dir(sftp, local_dir, remote_dir):
    try:
        sftp.stat(remote_dir)
    except FileNotFoundError:
        sftp.mkdir(remote_dir)

    for item in os.listdir(local_dir):
        if should_exclude(item):
            continue
        local_path = os.path.join(local_dir, item)
        remote_path = f"{remote_dir}/{item}"
        if os.path.isdir(local_path):
            upload_dir(sftp, local_path, remote_path)
        else:
            print(f"  uploading {remote_path}")
            sftp.put(local_path, remote_path)

def main():
    print(f"Connecting to {SSH_HOST}:{SSH_PORT}...")
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(SSH_HOST, port=SSH_PORT, username=SSH_USER, password=SSH_PASS)

    sftp = client.open_sftp()
    print(f"Deploying to {REMOTE_PATH}...")
    upload_dir(sftp, LOCAL_PATH, REMOTE_PATH)

    sftp.close()
    client.close()
    print("Deploy complete.")

if __name__ == "__main__":
    main()
