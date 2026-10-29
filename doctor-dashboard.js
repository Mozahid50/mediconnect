"use strict";

/* =========================================
   MediConnect Doctor Dashboard
   PHP + MySQL version
   ========================================= */

const API = {
    appointments: "doctor-get-appointment.php",
    updateAppointment: "doctor-update-appointment.php",
    reports: "doctor-get-reports.php",
    saveReport: "doctor-save-report.php",
    prescriptions: "doctor-get-prescriptions.php",
    savePrescription: "doctor-save-prescription.php"
};

let mysqlAppointments = [];
let mysqlReports = [];
let mysqlPrescriptions = [];

let appointmentsLoadFailed = false;
let reportsLoadFailed = false;
let prescriptionsLoadFailed = false;

let appointmentRequestRunning = false;
let reportRequestRunning = false;
let prescriptionRequestRunning = false;

let currentPage = "dashboard";
let toastTimer = null;

const pageInfo = {
    dashboard: [
        "Dashboard",
        "Your healthcare workspace at a glance"
    ],
    patients: [
        "My Patients",
        "Patient directory and visit history"
    ],
    appointments: [
        "Appointments",
        "Review and manage patient bookings"
    ],
    reports: [
        "Medical Reports",
        "Patient clinical records"
    ],
    prescriptions: [
        "Digital Prescriptions",
        "Treatment and medication records"
    ],
    video: [
        "Video Consultations",
        "Online appointment schedule"
    ]
};

/* =========================================
   GENERAL HELPERS
   ========================================= */

function $(id) {
    return document.getElementById(id);
}

function esc(value) {
    return String(value ?? "").replace(/[&<>"']/g, character => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;"
    })[character]);
}

function normal(value) {
    return String(value ?? "").trim().toLowerCase();
}

function setText(id, value) {
    const element = $(id);
    if (element) element.textContent = String(value ?? "");
}

function showToast(message, type = "success") {
    const toast = $("toast");
    if (!toast) {
        alert(message);
        return;
    }

    toast.textContent = message;
    toast.classList.remove("success", "error", "show");
    toast.classList.add(type === "error" ? "error" : "success");
    toast.classList.add("show");

    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toast.classList.remove("show");
    }, 3500);
}

async function readJSON(response) {
    const text = await response.text();

    let data;

    try {
        data = JSON.parse(text);
    } catch {
        throw new Error(
            "Server থেকে valid JSON পাওয়া যায়নি। PHP error বা endpoint path পরীক্ষা করো।"
        );
    }

    if (!response.ok || data.success === false) {
        throw new Error(
            data.message ||
            `Request failed (${response.status})`
        );
    }

    return data;
}

async function apiGet(url) {
    const response = await fetch(url, {
        method: "GET",
        credentials: "same-origin",
        cache: "no-store",
        headers: {
            "Accept": "application/json"
        }
    });

    return readJSON(response);
}

async function apiPost(url, body) {
    const response = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        cache: "no-store",
        headers: {
            "Accept": "application/json"
        },
        body
    });

    return readJSON(response);
}

function formatDate(value) {
    if (!value) return "—";

    const raw = String(value).slice(0, 10);
    const parts = raw.split("-");

    if (parts.length !== 3) return esc(value);

    const date = new Date(
        Number(parts[0]),
        Number(parts[1]) - 1,
        Number(parts[2])
    );

    if (Number.isNaN(date.getTime())) return esc(value);

    return date.toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric"
    });
}

function statusOf(record) {
    const status = normal(record.status);

    if (["approved", "approve", "confirmed", "accepted"].includes(status)) {
        return "Confirmed";
    }

    if (["rejected", "cancelled", "canceled"].includes(status)) {
        return "Cancelled";
    }

    if (status === "completed") return "Completed";
    if (status === "available") return "Available";
    if (status === "draft") return "Draft";

    return "Pending";
}

function statusHTML(value) {
    const status = statusOf({ status: value });
    const className = status.toLowerCase();

    return `
        <span class="status-badge status-${esc(className)}">
            ${esc(status)}
        </span>
    `;
}

function emptyRow(message, colspan = 6) {
    return `
        <tr>
            <td colspan="${colspan}" class="empty-state">
                ${esc(message)}
            </td>
        </tr>
    `;
}

function emptyBlock(message) {
    return `
        <div class="empty-state">
            ${esc(message)}
        </div>
    `;
}

function patientName(record) {
    return (
        record.patient_name ||
        record.patientName ||
        record.name ||
        "Unknown patient"
    );
}

function patientCode(record) {
    return (
        record.patient_code ||
        record.patientCode ||
        record.patient_public_id ||
        record.patientId ||
        record.patient_id ||
        "—"
    );
}

function appointmentDate(record) {
    return record.date ||
        record.appointment_date ||
        record.appointmentDate ||
        "";
}

function appointmentTime(record) {
    return record.time ||
        record.appointment_time ||
        record.appointmentTime ||
        "";
}

function appointmentReason(record) {
    return record.type ||
        record.reason ||
        record.visit_type ||
        "General consultation";
}

function searchable(values, query) {
    const text = normal(query);
    if (!text) return true;

    return values.some(value => normal(value).includes(text));
}

/* =========================================
   SIDEBAR / NAVIGATION
   ========================================= */

function toggleSidebar() {
    const sidebar = $("sidebar");
    if (sidebar) sidebar.classList.toggle("open");
}

function openPage(page, clickedButton = null) {
    if (!pageInfo[page]) return;

    currentPage = page;

    document.querySelectorAll(".page").forEach(section => {
        section.classList.toggle(
            "active",
            section.id === `page-${page}`
        );
    });

    document.querySelectorAll(".nav-item").forEach(button => {
        button.classList.toggle(
            "active",
            button.dataset.page === page
        );
    });

    setText("topTitle", pageInfo[page][0]);
    setText("topSubtitle", pageInfo[page][1]);

    const sidebar = $("sidebar");
    if (sidebar) sidebar.classList.remove("open");

    if (page === "dashboard") renderDashboard();
    if (page === "patients") renderPatients();
    if (page === "appointments") renderAppointments();
    if (page === "reports") renderReports();
    if (page === "prescriptions") renderPrescriptions();
    if (page === "video") renderVideoAppointments();
}

function toggleForm(id, force) {
    const form = $(id);
    if (!form) return;

    const shouldOpen = typeof force === "boolean"
        ? force
        : !form.classList.contains("open");

    form.classList.toggle("open", shouldOpen);
}

function showForm(id) {
    toggleForm(id, true);

    const form = $(id);
    if (form) {
        form.scrollIntoView({
            behavior: "smooth",
            block: "start"
        });
    }
}

function logoutDoctor() {
    // Server-side session logout should be handled by logout.php.
    window.location.href = "logout.php";
}

/* =========================================
   APPOINTMENTS: LOAD FROM MYSQL
   ========================================= */

async function loadAppointmentsFromMySQL() {
    if (appointmentRequestRunning) return;

    appointmentRequestRunning = true;

    try {
        const data = await apiGet(API.appointments);

        console.log("Appointment API response:", data);

        if (!Array.isArray(data.appointments)) {
            throw new Error(
                data.message ||
                "API response-এ appointments array পাওয়া যায়নি।"
            );
        }

        mysqlAppointments = data.appointments.map(item => ({
            ...item,
            id: Number(item.id || 0),
            patient_db_id: item.patient_db_id ?? item.patient_id ?? "",
            patient_code: item.patient_code ?? item.patientCode ?? "",
            patient_name: item.patient_name ?? item.patientName ?? item.name ?? "",
            phone: item.phone ?? "",
            email: item.email ?? "",
            date: item.date ?? item.appointment_date ?? item.appointmentDate ?? "",
            time: item.time ?? item.appointment_time ?? item.appointmentTime ?? "",
            type: item.type ?? item.reason ?? item.visit_type ?? "General consultation",
            status: item.status ?? "Pending"
        }));

        appointmentsLoadFailed = false;

    } catch (error) {
        appointmentsLoadFailed = true;

        console.error("Appointment load error:", error);

        showToast(
            "Appointment load হয়নি: " + error.message,
            "error"
        );

    } finally {
        appointmentRequestRunning = false;

        renderDashboard();
        renderPatients();
        renderAppointments();
        renderVideoAppointments();
    }
}

function appointments() {
    return mysqlAppointments;
}

function renderAppointments() {
    const tbody = $("appointmentsTable");
    if (!tbody) return;

    if (appointmentsLoadFailed && !mysqlAppointments.length) {
        tbody.innerHTML = emptyRow(
            "Appointment load হয়নি। আবার চেষ্টা করো অথবা PHP endpoint পরীক্ষা করো।",
            7
        );
        return;
    }

    const query = $("appointmentSearch")?.value || "";
    const filter = $("appointmentFilter")?.value || "all";

    const rows = mysqlAppointments.filter(item => {
        const status = statusOf(item);

        return searchable([
            patientName(item),
            patientCode(item),
            item.phone,
            item.email,
            appointmentReason(item),
            appointmentDate(item),
            appointmentTime(item),
            item.status
        ], query) && (filter === "all" || status === filter);
    });

    if (!rows.length) {
        tbody.innerHTML = emptyRow(
            "কোনো appointment পাওয়া যায়নি।",
            7
        );
        return;
    }

    tbody.innerHTML = rows.map(item => {
        const id = Number(item.id);
        const status = statusOf(item);

        const contact = [item.phone, item.email]
            .filter(Boolean)
            .map(esc)
            .join("<br>");

        const canUpdate = status === "Pending" && id > 0;

        return `
            <tr>
                <td>
                    <strong>${esc(patientName(item))}</strong>
                    <small>${esc(patientCode(item))}</small>
                </td>
                <td>${contact || "—"}</td>
                <td>${formatDate(appointmentDate(item))}</td>
                <td>${esc(appointmentTime(item) || "—")}</td>
                <td>${esc(appointmentReason(item))}</td>
                <td>${statusHTML(status)}</td>
                <td>
                    ${
                        canUpdate
                            ? `
                                <button class="btn btn-primary btn-sm"
                                    data-appointment-action="confirm"
                                    data-appointment-id="${id}">
                                    Accept
                                </button>
                                <button class="btn btn-outline btn-sm"
                                    data-appointment-action="cancel"
                                    data-appointment-id="${id}">
                                    Cancel
                                </button>
                            `
                            : "—"
                    }
                </td>
            </tr>
        `;
    }).join("");
}
async function updateAppointment(id, action) {
    if (!Number.isInteger(Number(id)) || Number(id) < 1) {
        showToast("Invalid appointment ID.", "error");
        return;
    }

    const confirmation = action === "cancel"
        ? "এই appointment cancel করতে চাও?"
        : "এই appointment confirm করতে চাও?";

    if (!window.confirm(confirmation)) return;

    const body = new FormData();
    body.append("appointment_id", String(id));
    body.append("action", action);

    try {
        const result = await apiPost(API.updateAppointment, body);
        showToast(result.message || "Appointment updated.");
        await loadAppointmentsFromMySQL();
    } catch (error) {
        console.error("Appointment update error:", error);
        showToast(error.message, "error");
    }
}

document.addEventListener("click", event => {
    const button = event.target.closest(
        "[data-appointment-action]"
    );

    if (!button) return;

    updateAppointment(
        button.dataset.appointmentId,
        button.dataset.appointmentAction
    );
});

/* =========================================
   DASHBOARD SUMMARY
   ========================================= */

function uniquePatients() {
    const map = new Map();

    mysqlAppointments.forEach(item => {
        const key = String(
            item.patient_db_id ||
            item.patient_code ||
            patientName(item)
        );

        if (!map.has(key)) {
            map.set(key, item);
        }
    });

    mysqlReports.forEach(item => {
        const key = String(
            item.patient_db_id ||
            item.patient_code ||
            patientName(item)
        );

        if (!map.has(key)) {
            map.set(key, item);
        }
    });

    mysqlPrescriptions.forEach(item => {
        const key = String(
            item.patient_id ||
            item.patient_code ||
            patientName(item)
        );

        if (!map.has(key)) {
            map.set(key, item);
        }
    });

    return [...map.values()];
}

window.renderDashboard = function renderDashboard() {
    setText("statPatients", uniquePatients().length);
    setText("statAppointments", mysqlAppointments.length);
    setText("statReports", mysqlReports.length);
    setText("statPrescriptions", mysqlPrescriptions.length);

    const pending = mysqlAppointments.filter(
        item => statusOf(item) === "Pending"
    ).length;

    const dot = $("notificationDot");

    if (dot) {
        dot.style.display = pending > 0 ? "block" : "none";
        dot.title = `${pending} pending appointment(s)`;
    }

    const recent = $("recentAppointments");
    if (!recent) return;

    const items = [...mysqlAppointments]
        .sort((a, b) => {
            const dateA = `${appointmentDate(a)} ${appointmentTime(a)}`;
            const dateB = `${appointmentDate(b)} ${appointmentTime(b)}`;
            return dateB.localeCompare(dateA);
        })
        .slice(0, 5);

    if (!items.length) {
        recent.innerHTML = emptyRow(
            appointmentsLoadFailed
                ? "Appointments load করা যায়নি।"
                : "এখনো কোনো appointment নেই।",
            3
        );
        return;
    }

    recent.innerHTML = items.map(item => `
        <tr>
            <td><strong>${esc(patientName(item))}</strong></td>
            <td>
                ${formatDate(appointmentDate(item))}
                <br>
                <small>${esc(appointmentTime(item) || "—")}</small>
            </td>
            <td>${statusHTML(item.status)}</td>
        </tr>
    `).join("");
};

/* =========================================
   PATIENT DIRECTORY
   ========================================= */

function renderPatients() {
    const tbody = $("patientsTable");
    if (!tbody) return;

    const query = $("patientSearch")?.value || "";
    const patientMap = new Map();

    mysqlAppointments.forEach(item => {
        const key = String(
            item.patient_db_id ||
            item.patient_code ||
            patientName(item)
        );

        if (!patientMap.has(key)) {
            patientMap.set(key, {
                ...item,
                appointmentCount: 0,
                lastVisit: appointmentDate(item)
            });
        }

        const patient = patientMap.get(key);
        patient.appointmentCount++;

        if (appointmentDate(item) > patient.lastVisit) {
            patient.lastVisit = appointmentDate(item);
        }
    });

    [...mysqlReports, ...mysqlPrescriptions].forEach(item => {
        const key = String(
            item.patient_db_id ||
            item.patient_id ||
            item.patient_code ||
            patientName(item)
        );

        if (!patientMap.has(key)) {
            patientMap.set(key, {
                ...item,
                appointmentCount: 0,
                lastVisit: item.report_date || item.created_at || ""
            });
        }
    });

    const patients = [...patientMap.values()].filter(item =>
        searchable([
            patientName(item),
            patientCode(item),
            item.phone,
            item.email
        ], query)
    );

    setText(
        "patientCountLabel",
        `${patients.length} patient record(s)`
    );

    if (!patients.length) {
        tbody.innerHTML = emptyRow(
            "কোনো patient পাওয়া যায়নি।",
            5
        );
        return;
    }

    tbody.innerHTML = patients.map(item => `
        <tr>
            <td>${esc(patientName(item))}</td>
            <td>${esc(patientCode(item))}</td>
            <td>${esc(item.phone || "—")}</td>
            <td>${Number(item.appointmentCount || 0)}</td>
            <td>${formatDate(item.lastVisit)}</td>
        </tr>
    `).join("");
}

/* =========================================
   MEDICAL REPORTS
   ========================================= */

async function renderReports() {
    const container = $("reportsList");
    if (!container) return;

    if (reportRequestRunning) return;
    reportRequestRunning = true;

    container.innerHTML = emptyBlock("Medical reports load হচ্ছে...");

    try {
        const data = await apiGet(API.reports);

        if (!Array.isArray(data.reports)) {
            throw new Error(
                "Report API-তে reports array পাওয়া যায়নি।"
            );
        }

        mysqlReports = data.reports;
        reportsLoadFailed = false;
    } catch (error) {
        reportsLoadFailed = true;
        console.error("Medical reports load error:", error);
        container.innerHTML = emptyBlock(error.message);
        return;
    } finally {
        reportRequestRunning = false;
    }

    renderReportList();
    renderDashboard();
    renderPatients();
}

function renderReportList() {
    const container = $("reportsList");
    if (!container) return;

    const query = $("reportSearch")?.value || "";

    const reports = mysqlReports.filter(item =>
        searchable([
            item.report_title,
            item.report_type,
            item.patient_name,
            item.patient_code,
            item.notes,
            item.doctor_name
        ], query)
    );

    if (!reports.length) {
        container.innerHTML = emptyBlock(
            reportsLoadFailed
                ? "Report load করা যায়নি।"
                : "কোনো medical report পাওয়া যায়নি।"
        );
        return;
    }

    container.innerHTML = reports.map(item => {
        const id = Number(item.id);
        const filePath = String(item.file_path || "").trim();

        return `
            <article class="record-card">
                <div class="record-card-header">
                    <div>
                        <h3>${esc(item.report_title || "Medical Report")}</h3>
                        <p>
                            ${esc(item.patient_name || "Unknown patient")}
                            · ${esc(item.patient_code || "No patient code")}
                        </p>
                    </div>
                    <span class="record-date">
                        ${formatDate(item.report_date || item.created_at)}
                    </span>
                </div>

                <p>
                    <strong>Category:</strong>
                    ${esc(item.report_type || "General")}
                </p>

                <p class="record-notes">
                    ${esc(item.notes || "No notes")}
                </p>

                ${
                    filePath && id > 0
                        ? `
                            <a class="btn btn-outline"
                               href="doctor-view-report.php?id=${encodeURIComponent(id)}"
                               target="_blank"
                               rel="noopener">
                               View Report
                            </a>
                        `
                        : `
                            <span class="record-date">
                                No attached file
                            </span>
                        `
                }
            </article>
        `;
    }).join("");
}

/* =========================================
   SAVE MEDICAL REPORT
   ========================================= */

const reportForm = $("createReportForm");

if (reportForm) {
    reportForm.addEventListener("submit", async event => {
        event.preventDefault();

        const patientNameValue =
            $("reportPatientName")?.value.trim() || "";

        const patientIdValue =
            $("reportPatientId")?.value.trim() || "";

        const title =
            $("reportTitle")?.value.trim() || "";

        const category =
            $("reportCategory")?.value.trim() || "";

        const date =
            $("reportDate")?.value || "";

        const notes =
            $("reportFindings")?.value.trim() || "";

        if (
            !patientIdValue ||
            !patientNameValue ||
            !title ||
            !category ||
            !date ||
            !notes
        ) {
            showToast("সব required field পূরণ করো।", "error");
            return;
        }

        const body = new FormData();

        body.append("patient_id", patientIdValue);
        body.append("patient_name", patientNameValue);
        body.append("report_title", title);
        body.append("report_type", category);
        body.append("report_date", date);
        body.append("notes", notes);

        const submitButton = reportForm.querySelector(
            'button[type="submit"]'
        );

        if (submitButton) submitButton.disabled = true;

        try {
            const result = await apiPost(API.saveReport, body);

            showToast(result.message || "Medical report saved.");
            reportForm.reset();
            toggleForm("reportForm", false);

            await renderReports();
        } catch (error) {
            console.error("Save report error:", error);
            showToast(error.message, "error");
        } finally {
            if (submitButton) submitButton.disabled = false;
        }
    });
}

/* =========================================
   DIGITAL PRESCRIPTIONS
   ========================================= */

async function loadMySQLPrescriptions() {
    if (prescriptionRequestRunning) return;

    prescriptionRequestRunning = true;

    const container = $("prescriptionsList");

    try {
        if (container) {
            container.innerHTML = emptyBlock(
                "Prescriptions load হচ্ছে..."
            );
        }

        const data = await apiGet(API.prescriptions);

        if (!Array.isArray(data.prescriptions)) {
            throw new Error(
                "Prescription API-তে prescriptions array পাওয়া যায়নি।"
            );
        }

        mysqlPrescriptions = data.prescriptions;
        prescriptionsLoadFailed = false;
    } catch (error) {
        prescriptionsLoadFailed = true;
        console.error("Prescription load error:", error);

        if (container) {
            container.innerHTML = emptyBlock(error.message);
        }
    } finally {
        prescriptionRequestRunning = false;
    }

    renderPrescriptions();
    renderDashboard();
    renderPatients();
}

function renderPrescriptions() {
    const container = $("prescriptionsList");
    if (!container) return;

    const query = $("prescriptionSearch")?.value || "";

    const prescriptions = mysqlPrescriptions.filter(item =>
        searchable([
            item.patient_name,
            item.patient_code,
            item.diagnosis,
            item.medicines,
            item.dosage,
            item.frequency,
            item.duration,
            item.instructions
        ], query)
    );

    if (!prescriptions.length) {
        container.innerHTML = emptyBlock(
            prescriptionsLoadFailed
                ? "Prescription load করা যায়নি।"
                : "কোনো prescription পাওয়া যায়নি।"
        );
        return;
    }

    container.innerHTML = prescriptions.map(item => `
        <article class="record-card">
            <div class="record-card-header">
                <div>
                    <h3>${esc(item.patient_name || "Unknown patient")}</h3>
                    <p>${esc(item.patient_code || "No patient code")}</p>
                </div>
                <span class="record-date">
                    ${formatDate(item.created_at)}
                </span>
            </div>

            <p>
                <strong>Diagnosis:</strong>
                ${esc(item.diagnosis || "—")}
            </p>

            <p>
                <strong>Medicine:</strong>
                ${esc(item.medicines || "—")}
            </p>

            <p>
                <strong>Dosage:</strong>
                ${esc(item.dosage || "—")}
            </p>

            <p>
                <strong>Frequency:</strong>
                ${esc(item.frequency || "—")}
            </p>

            <p>
                <strong>Duration:</strong>
                ${esc(item.duration || "—")}
            </p>

            <p>
                <strong>Instructions:</strong>
                ${esc(item.instructions || "—")}
            </p>

            ${
                item.follow_up_date
                    ? `<p><strong>Follow-up:</strong> ${formatDate(item.follow_up_date)}</p>`
                    : ""
            }
        </article>
    `).join("");
}

/* =========================================
   SAVE DIGITAL PRESCRIPTION
   ========================================= */


const prescriptionForm = $("createPrescriptionForm");

if (prescriptionForm) {
    prescriptionForm.addEventListener("submit", async event => {
        event.preventDefault();

        const patientNameValue = $("presPatientName")?.value.trim() || "";
        const patientIdValue = $("presPatientId")?.value.trim() || "";
        const diagnosis = $("presDiagnosis")?.value.trim() || "";
        const medicines = $("presMedicine")?.value.trim() || "";
        const dosage = $("presDosage")?.value.trim() || "";
        const duration = $("presDuration")?.value.trim() || "";
        const instructions = $("presInstructions")?.value.trim() || "";

        if (!patientIdValue || !diagnosis || !medicines || !dosage || !duration) {
            showToast(
                "Patient ID, diagnosis, medicine, dosage এবং duration পূরণ করো।",
                "error"
            );
            return;
        }

        const body = new FormData();
        body.append("patient_name", patientNameValue);
        body.append("patient_id", patientIdValue);
        body.append("diagnosis", diagnosis);
        body.append("medicines", medicines);
        body.append("dosage", dosage);
        body.append("frequency", dosage);
        body.append("duration", duration);
        body.append("instructions", instructions);

        const submitButton = prescriptionForm.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;

        try {
            const result = await apiPost(API.savePrescription, body);

            showToast(result.message || "Prescription saved successfully.");

            prescriptionForm.reset();
            toggleForm("prescriptionForm", false);

            try {
                await loadMySQLPrescriptions();

                if (typeof renderDashboard === "function") {
                    renderDashboard();
                }

                if (typeof renderPrescriptions === "function") {
                    renderPrescriptions();
                }
            } catch (refreshError) {
                console.error("Prescription saved, but refresh failed:", refreshError);
                showToast(
                    "Prescription save হয়েছে, কিন্তু dashboard refresh করতে সমস্যা হয়েছে।",
                    "error"
                );
            }
        } catch (error) {
            console.error("Save prescription error:", error);
            showToast(error.message || "Prescription save করা যায়নি।", "error");
        } finally {
            if (submitButton) submitButton.disabled = false;
        }
    });
}

/* =========================================
   VIDEO CONSULTATIONS
   ========================================= */

function renderVideoAppointments() {
    const tbody = $("videoTable");
    if (!tbody) return;

    const query = $("videoSearch")?.value || "";

    // Show video/online appointments assigned to this doctor.
    // Do not assume every appointment reason contains "video".
    const videos = mysqlAppointments.filter(item => {
        const type = normal(appointmentReason(item));
const status = statusOf(item);

return status === "Confirmed" && searchable([
            patientName(item),
            patientCode(item),
            item.phone,
            appointmentReason(item)
        ], query);
    });

    if (!videos.length) {
        tbody.innerHTML = emptyRow(
            "কোনো video/online appointment পাওয়া যায়নি। Appointment-এর reason-এ video, online বা virtual থাকতে হবে।",
            5
        );
        return;
    }

    tbody.innerHTML = videos.map(item => {
        const status = statusOf(item);
        const appointmentId = Number(
            item.id ?? item.appointment_id ?? 0
        );

        let consultationAction = "";

        if (status === "Confirmed" && appointmentId > 0) {
            consultationAction = `
                <a class="btn btn-primary"
                   href="video-consultations.php?appointment_id=${appointmentId}">
                    <i class="fa-solid fa-video"></i>
                    Join Video Call
                </a>
            `;
        } else if (status === "Confirmed") {
            consultationAction = "Appointment ID unavailable";
        } else {
            consultationAction = "Waiting for confirmation";
        }

        return `
            <tr>
                <td>
                    <strong>${esc(patientName(item))}</strong>
                    <small>${esc(patientCode(item))}</small>
                </td>
                <td>${formatDate(appointmentDate(item))}</td>
                <td>${esc(appointmentTime(item) || "—")}</td>
                <td>${statusHTML(item.status)}</td>
                <td>${consultationAction}</td>
            </tr>
        `;
    }).join("");
}
/* =========================================
   INITIALIZATION
   ========================================= */

async function initializeDashboard() {
    const today = new Date();

    setText(
        "todayDate",
        today.toLocaleDateString("en-GB", {
            weekday: "short",
            day: "2-digit",
            month: "short",
            year: "numeric"
        })
    );

    setText("year", today.getFullYear());

    const reportDate = $("reportDate");

    if (reportDate && !reportDate.value) {
        reportDate.value =
            `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`;
    }

    renderDashboard();
    renderPatients();
    renderAppointments();
    renderVideoAppointments();
    renderReportList();
    renderPrescriptions();

    await Promise.allSettled([
        loadAppointmentsFromMySQL(),
        renderReports(),
        loadMySQLPrescriptions()
    ]);

    renderDashboard();
    renderPatients();
    renderAppointments();
    renderVideoAppointments();
}

document.addEventListener("DOMContentLoaded", initializeDashboard);