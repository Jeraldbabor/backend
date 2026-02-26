# CampusEye GateSystem — System Documentation

## Overview

CampusEye GateSystem is an RFID-based student attendance monitoring system. Students tap their RFID cards at the school gate kiosk to record their attendance. The system automatically sends notifications to parents and teachers through the mobile app.

## Architecture

```
┌────────────┐     ┌──────────────┐     ┌──────────────┐
│  USB RFID  │────▸│   Kiosk UI   │────▸│ Laravel API  │
│  Scanner   │     │  (Next.js)   │     │  (Backend)   │
└────────────┘     └──────────────┘     └──────┬───────┘
                                               │
                            ┌──────────────────┼──────────────────┐
                            │                  │                  │
                     ┌──────▸────────┐  ┌──────▸────────┐  ┌─────▸──────┐
                     │  PostgreSQL   │  │  Queue Worker  │  │ Mobile App │
                     │   Database    │  │ (Notifications)│  │ (Expo/RN)  │
                     └───────────────┘  └──────┬───────┘  └─────▴──────┘
                                               │                │
                                               └──────▸─────────┘
                                                Expo Push Service
```

## User Roles

| Role | Description |
|------|-------------|
| **Super Admin** | Global system administrator, manages all schools |
| **Admin** | School administrator, manages students/teachers/RFID |
| **Teacher** | Receives notifications when their students scan |
| **Principal** | School head — can view reports |
| **Parent** | Receives notifications when their child scans |
| **Student** | (Optional) Student account |

---

## Core Flow: RFID Attendance Scan

```
Student taps RFID card → Kiosk captures code → POST /api/kiosk/scan
    ↓
Backend:
  1. Lookup student by RFID code + school
  2. Throttle check (5 min cooldown)
  3. Determine direction (IN / OUT)
  4. Create AttendanceLog record
  5. Dispatch ProcessAttendanceScan job (async)
    ↓
Queue Worker:
  1. Create in-app notification for parent + teachers
  2. Fetch user device tokens from `device_tokens` table
  3. Dispatch remote push payload to Expo's Push APIs
    ↓
Mobile App:
  1. Receives remote push banner (lock screen)
  2. Polling/WebSocket updates in-app notification bell count
```

### Direction Logic

- **First scan of the day** → `IN`
- **Already scanned IN today** → `OUT`
- **Already scanned IN and OUT** → `IN` (next cycle)

### Throttle

Each student can only scan once every **5 minutes** to prevent accidental double-taps.

---

## API Endpoints

### Kiosk (API Key Auth via `X-Kiosk-Api-Key` header)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/kiosk/scan` | Process RFID scan |

### Admin (Sanctum Token Auth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/admin/students` | List students |
| `POST` | `/api/admin/students` | Create student |
| `GET` | `/api/admin/students/{id}` | Show student + history |
| `PUT` | `/api/admin/students/{id}` | Update student / assign RFID |
| `DELETE` | `/api/admin/students/{id}` | Delete student |
| `GET` | `/api/admin/teachers` | List teacher assignments |
| `POST` | `/api/admin/teachers` | Assign teacher to grade/section |
| `PUT` | `/api/admin/teachers/{id}` | Update assignment |
| `DELETE` | `/api/admin/teachers/{id}` | Remove assignment |
| `GET` | `/api/admin/attendance-logs` | List attendance logs |
| `GET` | `/api/admin/attendance-logs/export` | Export CSV |
| `GET` | `/api/admin/attendance-logs/{id}` | Show single log |

### Notifications (Sanctum Token Auth — for mobile app)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/notifications` | List notifications + unread count |
| `PATCH` | `/api/notifications/{id}/read` | Mark as read |
| `PATCH` | `/api/notifications/read-all` | Mark all as read |

### Push Tokens (Sanctum Token Auth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/mobile/push-token` | Register/Update a device Expo push token |
| `POST` | `/api/mobile/logout` | Custom logout to clear session + token (optional) |

---

## Database Schema

### `students`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `school_id` | FK→schools | Student's school |
| `parent_id` | FK→users (nullable) | Linked parent account |
| `first_name` | string | First name |
| `last_name` | string | Last name |
| `grade` | string | Grade level (e.g., "7") |
| `section` | string | Section (e.g., "A") |
| `rfid_code` | string (unique, nullable) | Assigned RFID card code |
| `student_id_number` | string (unique) | School ID number |

### `teachers`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `user_id` | FK→users | Teacher user account |
| `school_id` | FK→schools | School |
| `grade` | string | Assigned grade |
| `section` | string | Assigned section |

### `attendance_logs`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `student_id` | FK→students | Scanned student |
| `school_id` | FK→schools | School |
| `rfid_code` | string | RFID code used |
| `scanned_at` | timestamp | Exact scan time |
| `direction` | string (in/out) | Scan direction |

### `notifications`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `user_id` | FK→users | Recipient |
| `title` | string | Notification title |
| `body` | text | Notification body |
| `type` | string | Type (attendance_in, attendance_out) |
| `data` | JSON (nullable) | Additional data |
| `read_at` | timestamp (nullable) | When read |

### `device_tokens`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `user_id` | FK→users | Token owner |
| `expo_push_token` | string | Unique Expo push token |
| `device_name` | string | Device model name (e.g., "iPhone 13") |
| `created_at` | timestamp | Registration time |

---

## Security

### Middleware

| Middleware | Purpose |
|-----------|---------|
| `auth:sanctum` | Token-based auth for admin/user routes |
| `role:admin,superadmin` | Role-based access control |
| `kiosk` | API key validation for gate scanner |

### Kiosk Authentication

The kiosk uses a static **API key** (configured in `.env` as `KIOSK_API_KEY`) sent via the `X-Kiosk-Api-Key` HTTP header. This avoids requiring user login on unattended devices.

---

## Setup Instructions

### 1. Backend

```bash
cd backend

# Install dependencies
composer install

# Configure environment
cp .env.example .env
# Edit .env: set DB_*, KIOSK_API_KEY

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed admin users
php artisan db:seed

# Start server
php artisan serve

# Start queue worker (for async notifications)
php artisan queue:work
```

### 2. Frontend (Admin Dashboard + Kiosk)

```bash
cd frontend
npm install
npm run dev
```

- **Admin Dashboard**: `http://localhost:3000/admin/dashboard`
- **Login**: `http://localhost:3000/portal-campuseye3x101`
- **Kiosk**: `http://localhost:3000/kiosk`

### 3. Queue Worker

The notification system uses Laravel's queue to process notifications asynchronously. **You must run the queue worker** for notifications to be sent:

```bash
php artisan queue:work
```

---

## Admin Workflow

### Step 1: Create Parent Accounts
Go to **Users** → **Add User** → Role: `Parent`

### Step 2: Create Teacher Accounts
Go to **Users** → **Add User** → Role: `Teacher`

### Step 3: Assign Teachers to Grades/Sections
Go to **Teachers** → **Assign Teacher** → Select teacher, set grade + section

### Step 4: Enroll Students
Go to **Students** → **Add Student** → Fill in details, link parent

### Step 5: Assign RFID Cards
Go to **Students** → **Edit** → Tap the RFID card with the USB reader or type the code

### Step 6: Deploy Kiosk
Open `http://localhost:3000/kiosk` on the gate computer → Full-screen browser

---

## Kiosk Setup

1. Connect USB RFID reader to the kiosk computer
2. Open `http://localhost:3000/kiosk` in a full-screen browser (press F11)
3. Set environment variables in `.env.local`:
   ```
   NEXT_PUBLIC_API_URL=http://your-backend-server:8000/api
   NEXT_PUBLIC_KIOSK_API_KEY=your-kiosk-api-key
   NEXT_PUBLIC_SCHOOL_ID=1
   ```
4. The RFID reader types the card code as keyboard input and presses Enter
5. The kiosk automatically processes the scan and displays the result

---

## Mobile App Integration

The mobile app consumes the notification API:

```
GET /api/notifications
Authorization: Bearer <token>
```

Response includes:
- `notifications`: paginated list of notifications
- `unread_count`: number of unread notifications

Each notification contains:
- `title`: e.g., "✅ Student Arrived at School"
- `body`: e.g., "Your child John Doe has arrived at School Name at 7:30 AM"
- `type`: `attendance_in` or `attendance_out`
- `data`: JSON with student_id, school_name, direction, scanned_at, etc.

---

## Push Notification Setup (Expo)

To enable real-time push banners on physical devices:

1. **EAS Project ID**: The app is linked to a project ID in `app.json` under `extra.eas.projectId`.
2. **Device Registration**: The mobile app uses the `usePushNotifications` hook to request OS permissions and send the token to the backend upon login.
3. **Laravel SDK**: The backend uses the `expo-server-sdk-php` library to communicate with Expo's servers.

### Testing Push Banners
Since Expo Go (SDK 53+) has limited support for remote notifications, you must build a **Development Build** (APK for Android or a custom iOS build) using EAS:
```bash
cd mobile
npx eas build --profile development --platform android
```
Once the APK is installed on a real device, push banners will work instantly on student scans.
