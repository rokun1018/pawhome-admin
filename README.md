# PawHome: Admin panel + Staff panel (PHP + Supabase)

Your admin design, now saving to a real database. The pages look the same as your HTML version; they are just `.php` files now.

## Before you start: add your own design files

Your `assets` folder didn't come through when you uploaded, so copy these two files from your original project into this folder:

```
assets/css/style.css     <- your file
assets/js/app.js         <- your file
assets/js/backend.js     <- already here (connects your design to the database)
```

You don't need to edit `app.js`. `backend.js` loads after it and makes the forms and buttons save for real.

## Installing the Staff panel upgrade (if your admin panel is already live)

Do these in this order:

1. **Backup first:** Supabase > Table Editor > export `pets`, `applications`, `boarding`, `users` as CSV.
2. **Database:** Supabase > SQL Editor > New query > paste all of `database/upgrade-1-staff.sql` > **Run**. It only adds things; running it twice by accident is harmless.
3. **Code:** on GitHub, **Add file > Upload files** and drag in everything from this folder **except** `database`. Files with the same name are replaced; your `assets/css/style.css` and `assets/js/app.js` stay as they are.
4. Wait for Render to show **Live**, then open `https://your-site.onrender.com/staff/`.

New install from scratch? Run `schema.sql`, then `upgrade-1-staff.sql` (then `sample-data.sql` if you want test data).

## How the two panels work together

- **One database, one set of accounts.** The staff panel lives at `/staff/`; the admin panel at `/`.
- **Staff sign up themselves** at `/staff/login.php`. Their account is *waiting for approval* until a Super Admin presses the tick in **User Management** (the sidebar shows a count). Nobody can sign up as Super Admin.
- **Decisions are shared.** Approving an application in either panel marks the pet as adopted. Confirming boarding in either panel checks the kennel isn't double-booked.
- **Pets:** set kennel, caretaker, health and last checkup in the admin Pets form; staff see them in *Pets Under Care*. A "Health Check" care log updates the last checkup automatically.
- **Everything staff do** appears in the admin dashboard's Recent activity, and care logs can be downloaded from admin **Reports > Daily care logs**.
- Caretaker roles only see the staff panel. Admin roles see both (link in each panel).

## What's in the folder

| File | What it does |
|---|---|
| `database/schema.sql` | Creates the 9 tables and your Super Admin account. Run once in Supabase. |
| `database/sample-data.sql` | Optional. The pets, applications, bookings and questions from your design, for testing. |
| `includes/config.php` | Database connection, sign-in, roles and what each role can open. |
| `includes/layout.php` | The sidebar and top bar shared by every page. |
| `login.php`, `forgot-password.php`, `reset-password.php`, `logout.php` | Signing in and out. |
| `index.php` | Dashboard with live numbers. |
| `pets.php`, `adoptions.php`, `boarding.php` | Shelter pages. |
| `quiz-bank.php`, `generate-quiz.php`, `review-quiz.php` | Eligibility quiz and the AI generator. |
| `user-management.php`, `reports.php`, `report.php`, `settings.php` | Administration. |
| `search.php`, `photo.php` | Top bar search and stored photos. |
| `includes/shared.php` | Rules both panels share (approvals, kennel checks, sign-in). |
| `staff/` | The staff panel: `index.php`, `login.php`, its `api/` and `auth/` files, and your staff design (`style.css`, `script.js`, `auth.js`). |
| `database/upgrade-1-staff.sql` | Adds staff roles, care logs and pet care fields. Run once. |
| `Dockerfile` | Tells Render how to run PHP. Don't edit. |

## Hosting for free

Supabase stores the data. Render runs the PHP. Both are free.

### 1. Create the database on Supabase
1. Sign up at https://supabase.com and click **New project**.
2. Set a **database password** and save it somewhere safe.
3. Choose a **region**. Pick the same region you'll pick on Render in step 4 (Singapore is available on both). Pages load much faster when the two are close.
4. Wait about 2 minutes for the project to finish.
5. Open `database/schema.sql`. Near the bottom, change the name, email and password of your Super Admin account.
6. In Supabase, go to **SQL Editor** > **New query**, paste the whole file and click **Run**. You should see "Success".
7. Optional: do the same with `database/sample-data.sql` to get test data.

### 2. Copy your connection details
1. Click **Connect** at the top of the Supabase dashboard.
2. Choose **Session pooler**. Don't use "Direct connection"; it doesn't work from Render.
3. Note the host (ends in `pooler.supabase.com`), port `5432`, database `postgres`, and user (looks like `postgres.abcdefghij`).

### 3. Put the code on GitHub
1. Sign up at https://github.com and create a **new repository**. Private is fine.
2. Click **uploading an existing file** and drag in everything in this folder, including the `includes`, `assets` and `database` folders.
3. Click **Commit changes**.

The `database` folder and this README are left out of the live website automatically.

### 4. Run it on Render
1. Sign up at https://render.com with your GitHub account.
2. Click **New +** > **Web Service** and choose your repository.
3. Set **Language** to **Docker**, choose the **same region** as Supabase, and set **Instance type** to **Free**.
4. Under **Environment Variables**, add:

| Name | Value |
|---|---|
| `DB_HOST` | host from step 2 |
| `DB_PORT` | `5432` |
| `DB_NAME` | `postgres` |
| `DB_USER` | user from step 2 |
| `DB_PASS` | your database password |
| `APP_TIMEZONE` | `Asia/Dhaka` (or your own time zone) |

5. Click **Create Web Service** and wait for **Live**. The first build takes a few minutes.
6. Open your link (`https://your-name.onrender.com`) and sign in with the account from step 1.

To update the site later, change files on GitHub. Render rebuilds by itself.

## Optional extras

**AI question generator.** Create an API key at https://console.anthropic.com (paid by usage; generating a set of questions costs a fraction of a cent). Add it on Render as `AI_API_KEY`. To use a different model, add `AI_MODEL`.

**Forgot-password emails.** Free hosts can't send email on their own. Create a free account at https://www.brevo.com, verify your sender email, create an API key, and add `BREVO_API_KEY` and `MAIL_FROM` (your verified email) on Render. Until then, a Super Admin can set a new password for anyone in User Management.

## Roles

| Role | Admin panel | Staff panel |
|---|---|---|
| Super Admin | Everything | Everything |
| Shelter Manager | Pets, adoptions, boarding, quiz, reports; can delete | Every decision, health status, reports |
| Veterinarian | Pets | Change health status |
| Volunteer Coordinator | Pets, boarding | Confirm or decline boarding |
| Volunteer | Pets, boarding | Care logs |
| Senior Caretaker | (none) | Approve applications, confirm boarding, health status, reports |
| Caretaker | (none) | Confirm or decline boarding |
| Junior Caretaker | (none) | View everything |
| Lead Vet Liaison | (none) | Change health status |

Everyone who can sign in can view the staff panel and add care logs. To change any of this, edit the `ACCESS` list in `includes/config.php`.

## Good to know

- **Shelters** are edited in Supabase's **Table Editor** (`shelters` table). Capacity there drives the dashboard percentages.
- **Approving** an application marks the pet as adopted. Moving it away from approved puts the pet back to available.
- **Boarding** won't let two pets share a kennel on the same nights. Same-day check-out and check-in is allowed.
- **PDF reports** open a print-ready page. Choose "Save as PDF" in the print window.
- **Notification preferences** are saved now. The emails themselves get sent once the public site exists and email is set up.
- **For the public (user) site later:** new adoption applications go into the `applications` table. Save the applicant's quiz answers in `quiz_answers` as `{"question id": answer number}` and the quiz pass-rate report will work automatically.

## Free plan limits

- Render's free server sleeps after 15 minutes without visitors. The next visit takes 30 to 60 seconds to wake it. Staff stay signed in.
- Supabase pauses free projects after 7 days without activity. Click **Restore** in the dashboard to wake it.
