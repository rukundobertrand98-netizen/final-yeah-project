# KBS Online Bus Reservation System

**KBS Limited** — Public Transport Management for **Kigali City**, Rwanda.

Laravel 12 application with REST APIs (Sanctum), MTN Mobile Money integration, QR digital tickets, live bus tracking, and role-based dashboards for passengers, operators, drivers, and administrators.

## Requirements

- PHP 8.2+
- Composer
- MySQL (XAMPP) or SQLite
- Apache/Nginx or `php artisan serve`

## Quick Setup (XAMPP)

```bash
cd c:\xampp\htdocs\Online-bus-system
composer install
copy .env.example .env
php artisan key:generate
```

### MySQL (recommended for XAMPP)

Edit `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kbs_bus
DB_USERNAME=root
DB_PASSWORD=
```

Create database `kbs_bus` in phpMyAdmin, then:

```bash
php artisan migrate --seed
php artisan serve
```

Open: http://127.0.0.1:8000

### SQLite (quick test)

```env
DB_CONNECTION=sqlite
```

```bash
type nul > database\database.sqlite
php artisan migrate --seed
```

## Demo Accounts

| Role      | Email             | Password |
|-----------|-------------------|----------|
| Admin     | admin@kbs.rw      | password |
| Operator  | operator@kbs.rw   | password |
| Driver    | driver@kbs.rw     | password |
| Passenger | passenger@kbs.rw  | password |

## API Base URL

`http://127.0.0.1:8000/api/v1`

Authenticate with header: `Authorization: Bearer {token}`

### Key endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/register` | Passenger registration |
| POST | `/auth/login` | Get API token |
| GET | `/stops` | Kigali bus stops |
| GET | `/routes/search` | Search trips |
| POST | `/bookings` | Create booking |
| POST | `/bookings/{id}/pay-momo` | MTN MoMo payment |
| GET | `/tracking/bookings/{id}` | Track bus |
| GET | `/alerts` | Proximity notifications |
| GET | `/tickets/{id}/qr` | QR code image |

Driver, operator, and admin routes are under `/api/v1/driver`, `/operator`, `/admin` with role middleware.

## MTN MoMo

Sandbox mode is **enabled by default** (instant simulated payment). For production, set in `.env`:

```env
MTN_MOMO_SANDBOX=false
MTN_MOMO_BASE_URL=https://proxy.momoapi.mtn.com
MTN_MOMO_SUBSCRIPTION_KEY=your_key
MTN_MOMO_API_USER=your_user_uuid
MTN_MOMO_API_KEY=your_api_key
MTN_MOMO_TARGET_ENV=mtnrwanda
MTN_MOMO_CALLBACK_URL=https://yourdomain.com/api/momo/callback
```

## Features by Role

### Passengers
- Register, search routes, select seat, pay via MTN MoMo
- Digital ticket with QR code
- Live bus tracking and proximity alerts

### Operators
- Manage buses, routes, schedules, prices
- Assign drivers, monitor bookings, view reports

### Drivers
- View trips, start/end trip, update GPS
- Scan/verify QR tickets, passenger list, delay reports

### Admins
- Manage users, approve operators
- Analytics, live bus monitor, payments, complaints

## License

Proprietary — KBS Limited.
