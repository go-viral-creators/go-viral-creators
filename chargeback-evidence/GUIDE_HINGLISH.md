# False chargeback se kaise bachein (VIP number / UPC delivery)

*Yeh technical + practical guidance hai, legal advice nahi. Badi amount ya repeat fraud mein ek advocate se zaroor baat karo.*

## Asli problem
Chargeback mein jeet **proof** se hoti hai. Abhi aapka system UPC dene par sirf status badalta hai: koi delivery timestamp nahi, customer ko kya bheja gaya ka record nahi, customer ne terms accept kiye ka record nahi. Isliye bank/gateway ko dikhane ko kuch nahi.

## Ye system ab kya proof banata hai
| Proof | Kaise |
|---|---|
| Customer ne terms/no-refund accept kiya | checkbox + `terms_accepted` event (time, IP, device) |
| Payment asli customer ne ki | Cashfree txn ID + amount + `payment_success` event |
| UPC diya gaya | `upc_delivered` event + `upc_delivered_at` |
| Customer ko bheja gaya | email queue + WhatsApp message id ka log |
| **Customer ne UPC dekha** | private link `upc_view.php` -> `upc_revealed` event with IP + device (sabse strong) |
| Record badla nahi gaya | har row hash-chained; evidence pack par "Integrity PASSED" |

## Dispute aaye to (step by step)
1. Cashfree dashboard/email mein dispute ki **deadline** dekho (aam taur par kuch hi din) - miss hui to automatic loss.
2. `admin/evidence_pack.php?order_id=ID` kholo -> PDF save karo.
3. Saath attach karo: WhatsApp/email screenshots, customer ke saath chat, terms/refund/shipping policy page ke screenshots (date ke saath), invoice.
4. Dispute response mein seedha likho: "Digital product. UPC delivered on <date/time>, customer opened it from IP <ip> at <time>, device <ua>. Policy accepted at checkout." Emotional baat mat likho, facts do.
5. Cashfree se pucho ki aapke payment mode (card/UPI/netbanking) par dispute rules kya hain - rules mode ke hisaab se alag hote hain.

## Aage ke liye roakne ke upay
- **Pehle contact**: UPC dene ke 24-48 ghante baad WhatsApp par "Sab theek? Port ho gaya?" bhejo. Customer ka "yes" = bahut accha proof.
- Refund policy saaf likho: "UPC deliver hone ke baad refund nahi." Policy pages `pages` table se edit hote hain.
- Jo customer baar-baar dispute kare: unka phone/email/IP internal blocklist mein daalo (checkout par check).
- Description/billing descriptor aisa rakho jo customer pehchane (e.g. "VIPNUMBERGALLERY"), warna log "pehchana nahi" bolke dispute karte hain.
- Cashfree/bank ke built-in fraud/3DS settings par baat karo.

## Legal route (agar customer jaan-bujhkar jhooth bol raha hai)
- Customer ko **legal notice** (advocate se) bhejo, evidence pack ke saath.
- Cyber-crime complaint: cybercrime.gov.in / 1930 (online payment fraud/cheating, BNS cheating provisions). Ye aapki taraf se jaanch ke liye hai, sirf asli fraud par karo.
- Electronic evidence court mein tabhi chalta hai jab uska certificate ho (Bharatiya Sakshya Adhiniyam, sec. 63 - pehle Evidence Act 65B). Advocate se certificate format banwao; hash-chain log + PDF isme kaam aata hai.
- Consumer court mein bhi customer ko jawab dena padega agar wo wahan jaata hai - wahi evidence pack kaam aayega.
- Kuch jhooth/manipulated proof kabhi mat banao - ye aapko hi phansa dega. Sirf asli logs hi use karo.

## Zaroori security warning
Aapki `admin/config.php` mein DB password, Cashfree keys, WhatsApp API key aur webhook secret plain text mein hain. Agar ye file kisi ke saath share hui ho (jaise yeh zip), to **sab keys abhi rotate karo** aur config ko web-root ke bahar rakho / env vars use karo. Is repo mein maine config copy nahi kiya.
