<?php
session_start();

if (
    ($_SESSION['user_role'] ?? '') !== 'doctor' ||
    empty($_SESSION['doctor_id'])
) {
    http_response_code(401);
    exit('Please log in as a doctor.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctor Appointments | MediConnect</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    padding: 20px;
    font-family: Arial, sans-serif;
    color: #1f2937;
    background: #f5f7fb;
}

.container {
    max-width: 1200px;
    margin: auto;
    background: #fff;
    padding: 22px;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(0,0,0,.06);
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

h2 { margin: 0; font-size: 23px; }

button {
    border: 0;
    border-radius: 8px;
    background: #0d9488;
    color: white;
    padding: 10px 15px;
    cursor: pointer;
}

.table-wrap { overflow-x: auto; }

table {
    width: 100%;
    min-width: 800px;
    border-collapse: collapse;
}

th, td {
    padding: 13px 12px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    font-size: 14px;
}

th {
    background: #f8fafc;
    color: #475569;
}

.status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    background: #fef3c7;
    color: #92400e;
    font-size: 12px;
    font-weight: bold;
}

.status.approved,
.status.completed {
    background: #d1fae5;
    color: #065f46;
}

.status.rejected,
.status.cancelled {
    background: #fee2e2;
    color: #991b1b;
}

#message {
    padding: 14px 0;
    color: #64748b;
}

@media (max-width: 600px) {
    body { padding: 10px; }
    .container { padding: 14px; }
    h2 { font-size: 20px; }
}
</style>
</head>

<body>
<div class="container">
    <div class="header">
        <h2>My Appointments</h2>
        <button type="button" onclick="loadAppointments()">
            Refresh
        </button>
    </div>

    <div id="message">Appointments loading...</div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Patient</th>
                    <th>Patient ID</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Reason</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="appointmentRows"></tbody>
        </table>
    </div>
</div>

<script>
const rows = document.getElementById('appointmentRows');
const message = document.getElementById('message');

function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    })[char]);
}

function formatTime(value) {
    if (!value) return '—';
    const parts = value.split(':');
    if (parts.length < 2) return value;
    let hour = Number(parts[0]);
    const minute = parts[1];
    const suffix = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;
    return `${hour}:${minute} ${suffix}`;
}

async function loadAppointments() {
    message.textContent = 'Appointments loading...';
    rows.innerHTML = '';

    try {
        const response = await fetch('doctor-get-appointments.php', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Appointment load failed.');
        }

        if (!Array.isArray(data.appointments)) {
            throw new Error('Appointment data format is incorrect.');
        }

        if (data.appointments.length === 0) {
            message.textContent = 'No appointments found.';
            return;
        }

        rows.innerHTML = data.appointments.map(item => {
            const status = String(item.status || 'Pending');
            const statusClass = status.toLowerCase();

            return `
                <tr>
                    <td>${escapeHTML(item.id)}</td>
                    <td>${escapeHTML(item.patient_name)}</td>
                    <td>${escapeHTML(item.patient_code)}</td>
                    <td>${escapeHTML(item.date)}</td>
                    <td>${escapeHTML(formatTime(item.time))}</td>
                    <td>${escapeHTML(item.reason || '—')}</td>
                    <td>
                        <span class="status ${escapeHTML(statusClass)}">
                            ${escapeHTML(status)}
                        </span>
                    </td>
                </tr>
            `;
        }).join('');

        message.textContent =
            `Total appointments: ${data.appointments.length}`;

    } catch (error) {
        console.error('Appointment view error:', error);
        message.textContent = 'Appointment load failed: ' + error.message;
    }
}

loadAppointments();
</script>
</body>
</html>