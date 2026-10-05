# Richworks Talent Tracker

Tracks Richworks staff who have attended training, served as a speaker, and answered questions.

## Run

```
node server.js
```

Open http://localhost:3000 (change port with `PORT=4000 node server.js`). No `npm install` needed.

- `server.js`: dependency-free Node server, REST API (`/api/people`, `/api/records`: GET, POST, PATCH, DELETE)
- `richworks-tracker.html`: UI (add, view, edit, delete staff and records)
- `data/db.json`: data is stored here (created automatically). Back up this file to keep your data.
