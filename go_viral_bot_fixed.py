
import os
import threading
import time
import random
import requests
from bs4 import BeautifulSoup
from telegram.ext import ApplicationBuilder, CommandHandler
from firebase_admin import credentials, db, initialize_app
import schedule

# Load .env if available
try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    print("python-dotenv not found, using hardcoded values or environment variables")

# Initialize Firebase
cred = credentials.Certificate({
    "type": os.getenv("FIREBASE_TYPE", "service_account"),
    "project_id": os.getenv("FIREBASE_PROJECT_ID", "go-viral-creators"),
    "private_key_id": os.getenv("FIREBASE_PRIVATE_KEY_ID", "e45932bdd4f05eb08767c8f55e4052728088c102"),
    "private_key": os.getenv("FIREBASE_PRIVATE_KEY", "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n").replace("\n", "\n"),
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

TOKEN = os.getenv("TELEGRAM_BOT_TOKEN", "YOUR_BOT_TOKEN")

CAPTIONS = [
    "Chasing dreams and good vibes! ✨ #Motivation",
    "Life’s better when you’re laughing! 😄 #Happiness",
    "Keep it real, always! 💯 #BeYou",
    "Stay positive, work hard, make it happen! 🚀 #Inspiration"
]

def scrape_hashtags():
    url = "https://www.all-hashtag.com/top-hashtags.php"
    try:
        response = requests.get(url, headers={'User-Agent': 'Mozilla/5.0'})
        time.sleep(2)
        soup = BeautifulSoup(response.text, 'html.parser')
        hashtags = [tag.text.strip() for tag in soup.find_all('span', class_='hashtag')]
        db.reference('/hashtags').set(hashtags[:10])
        return hashtags[:10]
    except Exception as e:
        return f"Error scraping hashtags: {str(e)}"

def scrape_captions():
    return CAPTIONS

def scrape_reel_download_link(reel_url):
    try:
        url = f"https://snapinsta.app/?url={reel_url}"
        response = requests.get(url, headers={'User-Agent': 'Mozilla/5.0'})
        time.sleep(2)
        soup = BeautifulSoup(response.text, 'html.parser')
        download_link = soup.find('a', class_='download-button')['href']
        return download_link
    except Exception as e:
        return f"Error fetching download link: {str(e)}"

async def start(update, context):
    await update.message.reply_text(
        "Welcome to InstaBot! Use:
"
        "/trends - Get trending hashtags
"
        "/caption [keyword] - Get a caption (e.g., /caption motivation)
"
        "/download <reel_url> - Download a reel
"
        "Note: Reel downloads are subject to Instagram’s Terms of Service. Use responsibly."
    )

async def trends(update, context):
    hashtags = scrape_hashtags()
    if isinstance(hashtags, list):
        await update.message.reply_text("Trending Hashtags:
" + "\n".join(hashtags))
    else:
        await update.message.reply_text(hashtags)

async def caption(update, context):
    args = context.args
    if args:
        keyword = args[0].lower()
        filtered = [c for c in CAPTIONS if keyword in c.lower()]
        if filtered:
            await update.message.reply_text(random.choice(filtered))
        else:
            await update.message.reply_text(f"No captions found for '{keyword}'")
    else:
        await update.message.reply_text(random.choice(CAPTIONS))

async def download(update, context):
    try:
        reel_url = context.args[0]
        link = scrape_reel_download_link(reel_url)
        await update.message.reply_text(f"Download Link: {link}")
    except IndexError:
        await update.message.reply_text("Usage: /download <reel_url>")

def job():
    hashtags = scrape_hashtags()
    print(f"Updated {len(hashtags if isinstance(hashtags, list) else 0)} hashtags")

def run_scheduler():
    schedule.every().day.at("00:00").do(job)
    while True:
        schedule.run_pending()
        time.sleep(60)

def main():
    print("🚀 Bot starting...")
    threading.Thread(target=run_scheduler, daemon=True).start()
    hashtags = scrape_hashtags()
    print(f"Scraped {len(hashtags) if isinstance(hashtags, list) else 0} hashtags")

    app = ApplicationBuilder().token(TOKEN).build()
    app.add_handler(CommandHandler("start", start))
    app.add_handler(CommandHandler("trends", trends))
    app.add_handler(CommandHandler("caption", caption))
    app.add_handler(CommandHandler("download", download))

    print("✅ Bot is running.")
    app.run_polling()

if __name__ == "__main__":
    main()
