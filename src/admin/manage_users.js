/* * Requirement: Add interactivity and data management to the Admin Portal.
 * All data is fetched from and written to the PHP API at '../api/index.php'.
 */

// --- Global Data Store ---
let users = [];
let initialized = false;

// --- Element Selections ---
const userTableBody = document.getElementById("user-table-body");
const addUserForm = document.getElementById("add-user-form");
const changePasswordForm = document.getElementById("password-form");
const searchInput = document.getElementById("search-input");
const tableHeaders = document.querySelectorAll("#user-table thead th");

// --- UI Rendering Functions ---

function createUserRow(user) {
    const tr = document.createElement("tr");

    tr.innerHTML = `
        <td>${user.name}</td>
        <td>${user.email}</td>
        <td>${user.is_admin === 1 ? "Yes" : "No"}</td>
        <td>
            <button class="edit-btn" data-id="${user.id}">Edit</button>
            <button class="delete-btn" data-id="${user.id}">Delete</button>
        </td>
    `;
    return tr;
}

function renderTable(userArray) {
    userTableBody.innerHTML = "";
    userArray.forEach(user => {
        userTableBody.appendChild(createUserRow(user));
    });
}

// --- Event Handlers ---

async function handleChangePassword(event) {
    event.preventDefault();
    const currentPassword = document.getElementById("current-password").value;
    const newPassword = document.getElementById("new-password").value;
    const confirmPassword = document.getElementById("confirm-password").value;

    if (newPassword !== confirmPassword) {
        alert("Passwords do not match.");
        return;
    }
    if (newPassword.length < 8) {
        alert("Password must be at least 8 characters.");
        return;
    }

    try {
        const response = await fetch("../api/index.php?action=change_password", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: 1, current_password: currentPassword, new_password: newPassword })
        });
        const result = await response.json();

        if (response.ok && result.success) {
            alert("Password updated successfully!");
            changePasswordForm.reset();
        } else {
            alert(result.message || "An error occurred.");
        }
    } catch (error) {
        alert("An error occurred: " + error.message);
    }
}

async function handleAddUser(event) {
    event.preventDefault();
    const name = document.getElementById("user-name").value.trim();
    const email = document.getElementById("user-email").value.trim();
    const password = document.getElementById("default-password").value;
    const isAdmin = parseInt(document.getElementById("is-admin").value);

    if (!name || !email || !password) {
        alert("Please fill out all required fields.");
        return;
    }

    try {
        const response = await fetch("../api/index.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ name, email, password, is_admin: isAdmin })
        });
        const result = await response.json();

        if (response.status === 201 && result.success) {
            await loadUsersAndInitialize();
            addUserForm.reset();
        } else {
            alert(result.message || "An error occurred.");
        }
    } catch (error) {
        alert("An error occurred: " + error.message);
    }
}

async function handleTableClick(event) {
    const target = event.target;
    const id = target.dataset.id;

    if (target.classList.contains("delete-btn")) {
        try {
            const response = await fetch(`../api/index.php?id=${id}`, { method: "DELETE" });
            const result = await response.json();
            if (response.ok && result.success) {
                users = users.filter(user => user.id != id);
                renderTable(users);
            } else {
                alert(result.message);
            }
        } catch (error) { alert(error.message); }
    }

    else if (target.classList.contains("edit-btn")) {
        const user = users.find(u => u.id == id);
        const newName = prompt("Edit name:", user.name);
        const newEmail = prompt("Edit email:", user.email);
        const newIsAdmin = prompt("Admin? (1 = Yes, 0 = No):", user.is_admin);

        if (newName && newEmail) {
            const response = await fetch("../api/index.php", {
                method: "PUT",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ id, name: newName, email: newEmail, is_admin: parseInt(newIsAdmin) })
            });
            if (response.ok) await loadUsersAndInitialize();
        }
    }
}

function handleSearch() {
    const term = searchInput.value.toLowerCase();
    const filtered = users.filter(user =>
        user.name.toLowerCase().includes(term) || user.email.toLowerCase().includes(term)
    );
    renderTable(filtered);
}

function handleSort(event) {
    const th = event.currentTarget;
    const column = ["name", "email", "is_admin"][th.cellIndex];
    const newDir = th.dataset.sortDir === "asc" ? "desc" : "asc";
    th.dataset.sortDir = newDir;

    users.sort((a, b) => {
        const valA = a[column], valB = b[column];
        return newDir === "asc"
            ? (valA > valB ? 1 : -1)
            : (valA < valB ? 1 : -1);
    });
    renderTable(users);
}

// --- Initialization ---

async function loadUsersAndInitialize() {
    try {
        const response = await fetch("../api/index.php");
        const result = await response.json();
        users = result.data;
        renderTable(users);

        if (!initialized) {
            changePasswordForm.addEventListener("submit", handleChangePassword);
            addUserForm.addEventListener("submit", handleAddUser);
            userTableBody.addEventListener("click", handleTableClick);
            searchInput.addEventListener("input", handleSearch);
            tableHeaders.forEach(th => th.addEventListener("click", handleSort));
            initialized = true;
        }
    } catch (error) {
        console.error("Initialization error:", error);
    }
}

loadUsersAndInitialize();
