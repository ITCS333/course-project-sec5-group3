
let users = [];

var userTableBody       = document.getElementById("user-table-body");
var addUserForm         = document.getElementById("add-user-form");
var changePasswordForm  = document.getElementById("password-form");
var searchInput         = document.getElementById("search-input");
var tableHeaders        = document.querySelectorAll("#user-table thead th");


function createUserRow(user) {
  var tr = document.createElement("tr");

  var adminText = user.is_admin == 1 ? "Yes" : "No";

  tr.innerHTML =
    "<td>" + user.name  + "</td>" +
    "<td>" + user.email + "</td>" +
    "<td>" + adminText  + "</td>" +
    "<td>" +
      "<button class='edit-btn'   data-id='" + user.id + "'>Edit</button>"   +
      "<button class='delete-btn' data-id='" + user.id + "'>Delete</button>" +
    "</td>";

  return tr;
}

function renderTable(userArray) {
  userTableBody.innerHTML = "";
  userArray.forEach(function(user) {
    userTableBody.appendChild(createUserRow(user));
  });
}

function handleChangePassword(event) {
  event.preventDefault();

  var currentPassword = document.getElementById("current-password").value;
  var newPassword     = document.getElementById("new-password").value;
  var confirmPassword = document.getElementById("confirm-password").value;

  if (newPassword !== confirmPassword) {
    alert("Passwords do not match.");
    return;
  }
  if (newPassword.length < 8) {
    alert("Password must be at least 8 characters.");
    return;
  }

  document.getElementById("current-password").value = "";
  document.getElementById("new-password").value     = "";
  document.getElementById("confirm-password").value = "";

  fetch("../api/index.php?action=change_password", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id: 1, current_password: currentPassword, new_password: newPassword })
  })
    .then(function(response) { return response.json().then(function(result) { return { response: response, result: result }; }); })
    .then(function(obj) {
      if (obj.response.ok && obj.result.success) {
        alert("Password updated successfully!");
      } else {
        alert(obj.result.message || "An error occurred.");
      }
    })
    .catch(function(error) {
      alert("An error occurred: " + error.message);
    });
}

function handleAddUser(event) {
  event.preventDefault();

  var name     = document.getElementById("user-name").value.trim();
  var email    = document.getElementById("user-email").value.trim();
  var password = document.getElementById("default-password").value;
  var isAdmin  = parseInt(document.getElementById("is-admin").value);

  if (!name || !email || !password) {
    alert("Please fill out all required fields.");
    return;
  }
  if (password.length < 8) {
    alert("Password must be at least 8 characters.");
    return;
  }

  fetch("../api/index.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ name: name, email: email, password: password, is_admin: isAdmin })
  })
    .then(function(response) { return response.json().then(function(result) { return { response: response, result: result }; }); })
    .then(function(obj) {
      if (obj.response.status === 201 && obj.result.success) {
        loadUsersAndInitialize();
        addUserForm.reset();
      } else {
        alert(obj.result.message || "An error occurred.");
      }
    })
    .catch(function(error) {
      alert("An error occurred: " + error.message);
    });
}

function handleTableClick(event) {
  var target = event.target;
  var id     = target.dataset.id;

  if (target.classList.contains("delete-btn")) {
    fetch("../api/index.php?id=" + id, { method: "DELETE" })
      .then(function(response) { return response.json().then(function(result) { return { response: response, result: result }; }); })
      .then(function(obj) {
        if (obj.response.ok && obj.result.success) {
          users = users.filter(function(u) { return u.id != id; });
          renderTable(users);
        } else {
          alert(obj.result.message);
        }
      })
      .catch(function(error) { alert(error.message); });
  }

  else if (target.classList.contains("edit-btn")) {
    var user     = users.find(function(u) { return u.id == id; });
    var newName  = prompt("Edit name:",               user.name);
    var newEmail = prompt("Edit email:",              user.email);
    var newAdmin = prompt("Admin? (1 = Yes, 0 = No):", user.is_admin);

    if (newName && newEmail) {
      fetch("../api/index.php", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: id, name: newName, email: newEmail, is_admin: parseInt(newAdmin) })
      })
        .then(function(response) {
          if (response.ok) loadUsersAndInitialize();
        });
    }
  }
}

function handleSearch(event) {
  var term = searchInput.value.toLowerCase();

  if (!term) {
    renderTable(users);
    return;
  }

  var filtered = users.filter(function(user) {
    return user.name.toLowerCase().includes(term) ||
           user.email.toLowerCase().includes(term);
  });

  renderTable(filtered);
}

function handleSort(event) {
  var th      = event.currentTarget;
  var columns = ["name", "email", "is_admin"];
  var column  = columns[th.cellIndex];
  var newDir  = th.dataset.sortDir === "asc" ? "desc" : "asc";
  th.dataset.sortDir = newDir;

  users.sort(function(a, b) {
    var valA = a[column];
    var valB = b[column];
    var cmp;

    if (column === "is_admin") {
      cmp = valA - valB;
    } else {
      cmp = String(valA).localeCompare(String(valB));
    }

    return newDir === "asc" ? cmp : -cmp;
  });

  renderTable(users);
}

async function loadUsersAndInitialize() {
  try {
    var response = await fetch("../api/index.php");

    if (!response.ok) {
      console.error("Failed to load users");
      alert("Failed to load users.");
      return;
    }

    var result = await response.json();
    users = result.data;
    renderTable(users);

    if (!loadUsersAndInitialize._listenersAttached) {
      changePasswordForm.addEventListener("submit", handleChangePassword);
      addUserForm.addEventListener("submit", handleAddUser);
      userTableBody.addEventListener("click", handleTableClick);
      searchInput.addEventListener("input", handleSearch);
      tableHeaders.forEach(function(th) {
        th.addEventListener("click", handleSort);
      });
      loadUsersAndInitialize._listenersAttached = true;
    }

  } catch (error) {
    console.error("Initialization error:", error);
  }
}

loadUsersAndInitialize._listenersAttached = false;


loadUsersAndInitialize();
