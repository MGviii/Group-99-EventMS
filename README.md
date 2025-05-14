# Group-99-EventMS
This is the repository for USSD and SMS based Mini-Project of Event Management System

# USSD-Based Event Registration System

A mobile-friendly USSD application that allows users to browse events, register with their name and phone number, make payments via mobile money, and receive a confirmation — all without needing internet access.

--

## Features

- *Browse Events*: View a list of upcoming events via USSD menu
- *Register*: Submit name and phone number for a selected event
-  *Mobile Payment*: Integrate with mobile money (e.g. MTN MoMo)
-  *Confirmation*: Display success message and optionally send SMS receipt

---

##  User Flow

1. Dial USSD code *384*75455#
2. Select "Browse Events"
3. Choose an event from the list
4. Enter:
   - Full Name
   - Phone Number (optional if already from SIM)
5. Proceed to payment via mobile money prompt
6. Receive confirmation screen + optional SMS

---

## Tech Stack

- *USSD Gateway*: Africa's Talking
- *Backend*: PHP 
- *Database*: MySQL
- *Payment Gateway*: ex:MTN MoMo
- *SMS Service*: Africa's Talking
---

## Setup Instructions

1. Clone the repository:

```bash
git clone https://github.com/MGviii/Group-99-EventMS.git
