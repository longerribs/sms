Here is a cleaner prompt you can paste into your agent:

Act as a senior full-stack product engineer, database designer, UI/UX designer, and backend architect.

I want to transform my current SMS system into a real SMS platform.

Current situation:

* Users can send SMS, but they cannot create accounts yet.
* The system currently has a client table with `email`, `name`, and `password`.
* I want to use those existing fields to support account creation and login.
* When an account is created, the system should automatically generate an API key and store it in the relevant table.
* The database and UI must be updated so everything aligns cleanly.

What I want you to build or design:

1. Account system

* Allow users to register accounts.
* Use the existing client table fields: `email`, `name`, and `password`.
* Add proper authentication and login flow.
* Auto-generate an API key after account creation.
* Store the API key securely in the correct related table.
* Let the user view their API key inside a Developer tab.

2. Developer tab

* Add a Developer tab in the dashboard.
* Show the user’s API key clearly.
* Include a testing section.
* Add endpoint examples.
* Show request methods involved.
* Include cURL examples.
* Include PHP examples.
* Show integration notes for developers.
* Add documentation for how to send SMS through the platform.

3. Account dashboard tabs

* Profile Manager tab.
* Personal Information tab for updating user details.
* Account Info tab showing:

  * date joined
  * account age
  * last profile update
  * SMS balance
* Performance status shown as a percentage.
* Clear display of usage and account activity.
    
4. Design goals

* Keep the system simple, secure, and production-ready.
* Make the UI clean and developer-friendly.
* Make the account flow easy for users to understand.
* Make the database structure normalized and practical.
* Make sure the prompt output aligns well with the actual tables, relationships, and functionality.

Deliverables required:

* Updated system architecture
* Updated database schema
* User registration/login flow
* API key generation flow
* Developer tab content and layout
* Account info and profile manager design
* SMS balance display logic
* Performance percentage logic
* Endpoint documentation with cURL and PHP examples
* Any required backend and frontend changes

