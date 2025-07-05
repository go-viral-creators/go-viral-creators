import os
try:
    from dotenv import load_dotenv
    load_dotenv()  # Load .env file if available
except ImportError:
    print("python-dotenv not found, using hardcoded values or environment variables")

import telegram
from telegram.ext import Application, CommandHandler  # Updated to use Application instead of Updater
from telegram.ext import filters  # Filters ko yahan se import karein
import requests
from bs4 import BeautifulSoup
import re
import random
from firebase_admin import credentials, db, initialize_app
import schedule
import time
import threading

# Initialize Firebase (fallback to hardcoded values if .env not found)
cred = credentials.Certificate({
    "type": os.getenv("FIREBASE_TYPE", "service_account"),
    "project_id": os.getenv("FIREBASE_PROJECT_ID", "go-viral-creators"),
    "private_key_id": os.getenv("FIREBASE_PRIVATE_KEY_ID", "e45932bdd4f05eb08767c8f55e4052728088c102"),
    "private_key": os.getenv("FIREBASE_PRIVATE_KEY", """-----BEGIN PRIVATE KEY-----\nMIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQCjpnymE1rzXmPK\nGWhBSJaP8qEsClRYhaNaRZrkX5BS9NU62IcyWFHabU/8FsJ2T01Mg8BfzQwo+gQR\nMVTjiNYEBomamXRS3aOLQDgmTsYIERv3pOG1eKE+LglTnX9oyxzD10O5EoqStbjq\nW5JARd8TlyaJ98On+8peX++ZN4e2tTD5gddIPYDphLfa/ke1rPjzvcCv8HmTRNck\nwDNlnfwmde7vnlh0oVkOOVvktQme15R3qcDtpIdh/KfEZRSZSpvP5xyQhuDFgokc\ngojczYRundM6DHN/k3AtluhODdO2AdIVhXZYZIM4IsfQWT8QQ8cfSfG2VyaYF83z\naBYl90CTAgMBAAECggEATwQWgGutCQAkx5Kv84GUbQmWR3jvU9Sm1HHyQV1hBqT/\nhcvBr43Ua9ZG/e2RVGWi6ZRd4QPU4L4jE5gRqFFMIKJ1c2evskkg654E3n4/gOqd\n6ds7Pg9yL180EqLjv1n+2BKKuQQaU48Zq1KukYkLFl7vdUNjvarGAf5pZj3ZMd6R\nuL8e3dpKvB5fwVfjqMsJwki9HYUAmChuvKb1L9B1kWe/lWXimHjmL5Cacqe/A2KQ\nrgBL7HX2uA9OvVIHTUObVElQxu8kyI+N7NJlRToL2GAuGLPeG9zn5qEh8BPMoQ2h\nggbjsexfivb9ycJ1zbAf0vhnrXgDLDWYtEYSw0QORQKBgQDZFFt9q+XerhlC0F7z\nFdoqqqGVjFNj3Kqj/KhZJ9iY3Az8gIUGG2NJLU9HDHpJxrfZdv2hS1CAraaTNEdC\nUnfhafBi8WkW2CWPrY3cyoD03pTUHe4QQTS/qx5wBqK+cc0Vj2nmDLjmmKnEYROX\nBRLf5PRm3FeJavdYVSYDhFdXpwKBgQDA/c5FmqJPFy/uWOCFU04wHOS5P4xBpTuM\n2vCpQu5uFombYQOl9QrMMmRLe7iprRy1UqRI64Q4v/ID4ENjDRrYySKsmCvvyIat\n2CQZi85i9VXipUqKg+qjdLicpHT3sKsTBKiJUcHz9hPHkuIBeIaRy5tTnwtPp6VE\niEqI5fBtNQKBgGKEsa0MSavRGZfQF3d9EGFSxTio0eO9bxgzf3fO9KcTHzWtYjHO\nVjYMwTD+gbVf4WppbVw5YRS1OqcOD9UStmNv/+/3nfkHKazWWE6+/N2k8qh92OA5\np6XhFFRFPrDt1uSVDLuiRHwrBskgQZLFc7Z3I1BXacbs68qEAleQRU97AoGAILjo\nCJU3gAGGdvtK1lBRqYa8oUxNE7RYbIGS3KAknTXlDxtb6v+pXQYOS14m6V4YyAXD\nJToJqTWia1XTFzBZpPg1kN4cVQPDEibbuBkkXVMLxOoRwXqshhp8UtLvoi+qUgcw\nLFhYcmz+3Y/iEi3FY2MKaxtEZ7UdKImTWbLjGSECgYBFVdc4QW2j396zPKfWyAWN\ng1NmeFKng9uv0RBkVChDYV+Ni1nAb1QyXFMZIoNUW0IjtXJuj8t5j7aHV7pbNfg0\nCXpu3m17YScUYS7CVTRYkQ6ZHUXwZtY9gCIhP0n77i6ZbxAkjjQXCcgKIztUAeBu\nEI99hTLEOPjHFZZ3mPeyaQ==\n-----END PRIVATE KEY-----\n"""),
    "client_email": os.getenv("FIREBASE_CLIENT_EMAIL", "firebase-adminsdk-fbsvc@go-viral-creators.iam.gserviceaccount.com"),
    "client_id": os.getenv("FIREBASE_CLIENT_ID", "117577953098029075168"),
    "auth_uri": os.getenv("FIREBASE_AUTH_URI", "https://accounts.google.com/o/oauth2/auth"),
    "token_uri": os.getenv("FIREBASE_TOKEN_URI", "https://oauth2.googleapis.com/token"),
    "auth_provider_x509_cert_url": os.getenv("FIREBASE_AUTH_PROVIDER_X509_CERT_URL", "https://www.googleapis.com/oauth2/v1/certs"),
    "client_x509_cert_url": os.getenv("FIREBASE_CLIENT_X509_CERT_URL", "https://www.googleapis.com/robot/v1/metadata/x509/firebase-adminsdk-fbsvc%40go-viral-creators.iam.gserviceaccount.com"),
    "universe_domain": os.getenv("FIREBASE_UNIVERSE_DOMAIN", "googleapis.com")
})
initialize_app(cred, {
    'databaseURL': os.getenv("FIREBASE_DATABASE_URL", "https://go-viral-creators-default-rtdb.firebaseio.com/")
})

# Telegram Bot Token from environment variable
TOKEN = os.getenv("TELEGRAM_BOT_TOKEN", "7788402909:AAG7fyHmolwJm-uZDczDXgDCk79KVxTEVu8")

# Static captions as fallback
CAPTIONS = [
    "Chasing dreams and good vibes! ✨ #Motivation",
    "Life’s better when you’re laughing! 😄 #Happiness",
    "Keep it real, always! 💯 #BeYou",
    "Stay positive, work hard, make it happen! 🚀 #Inspiration"
]

# Scrape trending hashtags
def scrape_hashtags():
    url = "https://www.all-hashtag.com/top-hashtags.php"  # Replace with reliable URL
    try:
        response = requests.get(url, headers={'User-Agent': 'Mozilla/5.0'})
        time.sleep(2)  # Prevent rate limiting
        soup = BeautifulSoup(response.text, 'html.parser')
        hashtags = []
        # Adjust based on actual HTML structure (inspect the site)
        for tag in soup.find_all('span', class_='hashtag'):  # Update class
            hashtags.append(tag.text.strip())
        # Store in Firebase
        ref = db.reference('/hashtags')
        ref.set(hashtags[:10])
        return hashtags[:10]
    except Exception as e:
        return f"Error scraping hashtags: {str(e)}"

# Scrape captions
def scrape_captions():
    url = "https://captionspack.com/captions"  # Replace with actual URL
    try:
        response = requests.get(url, headers={'User-Agent': 'Mozilla/5.0'})
        time.sleep(2)  # Prevent rate limiting
        soup = BeautifulSoup(response.text, 'html.parser')
        captions = []
        # Adjust based on actual HTML structure (inspect the site)
        for caption in soup.find_all('p', class_='caption'):  # Update class
            text = caption.text.strip()
            if text and len(text) > 10:  # Filter out short or empty captions
                captions.append(text)
        # Store in Firebase
        ref = db.reference('/captions')
        ref.set(captions[:50])
        return captions[:50]
    except Exception as e:
        return f"Error scraping captions: {str(e)}"

# Scrape reel download link
def scrape_reel_download_link(reel_url):
    try:
        download_site = f"https://snapinsta.app/?url={reel_url}"  # Replace with working URL
        response = requests.get(download_site, headers={'User-Agent': 'Mozilla/5.0'})
        time.sleep(2)  # Prevent rate limiting
        soup = BeautifulSoup(response.text, 'html.parser')
        # Adjust based on actual HTML structure
        download_link = soup.find('a', class_='download-button')['href']  # Update class
        return download_link
    except Exception as e:
        return f"Error fetching download link: {str(e)}"

# Bot commands
async def start(update, context):  # Added async for Application
    await update.message.reply_text(
        "Welcome to InstaBot! Use:\n"
        "/trends - Get trending hashtags\n"
        "/caption [keyword] - Get a caption (e.g., /caption motivation)\n"
        "/download <reel_url> - Download a reel\n"
        "Note: Reel downloads are subject to Instagram’s Terms of Service. Use responsibly."
    )

async def trends(update, context):  # Added async
    hashtags = scrape_hashtags()
    if isinstance(hashtags, list):
        await update.message.reply_text("Trending Hashtags:\n" + "\n".join(hashtags))
    else:
        await update.message.reply_text(hashtags)

async def caption(update, context):  # Added async
    args = context.args
    ref = db.reference('/captions')
    captions = ref.get() or CAPTIONS  # Fallback to static list if Firebase empty
    if args:
        keyword = args[0].lower()
        filtered_captions = [c for c in captions if keyword in c.lower()]
        if filtered_captions:
            await update.message.reply_text(random.choice(filtered_captions))
        else:
            await update.message.reply_text(f"No captions found for '{keyword}'. Try another keyword.")
    else:
        await update.message.reply_text(random.choice(captions))

async def download(update, context):  # Added async
    try:
        reel_url = context.args[0]
        download_link = scrape_reel_download_link(reel_url)
        await update.message.reply_text(f"Download Link: {download_link}")
    except IndexError:
        await update.message.reply_text("Please provide a reel URL: /download <reel_url>")

# Scheduler for daily scraping
def job():
    captions = scrape_captions()
    hashtags = scrape_hashtags()
    print(f"Updated {len(captions if isinstance(captions, list) else 0)} captions, "
          f"{len(hashtags if isinstance(hashtags, list) else 0)} hashtags")

schedule.every().day.at("00:00").do(job)

def run_scheduler():
    while True:
        schedule.run_pending()
        time.sleep(60)

def main():
    # Start scheduler in a separate thread
    threading.Thread(target=run_scheduler, daemon=True).start()

    # Scrape captions and hashtags on startup
    captions = scrape_captions()
    if isinstance(captions, list):
        print(f"Scraped {len(captions)} captions")
    else:
        print(captions)
    hashtags = scrape_hashtags()
    if isinstance(hashtags, list):
        print(f"Scraped {len(hashtags)} hashtags")
    else:
        print(hashtags)

    # Use Application instead of Updater
    application = Application.builder().token(TOKEN).build()

    # Add handlers
    application.add_handler(CommandHandler("start", start))
    application.add_handler(CommandHandler("trends", trends))
    application.add_handler(CommandHandler("caption", caption))
    application.add_handler(CommandHandler("download", download))

    # Start the bot
    application.run_polling()

if __name__ == "__main__":
    main()
