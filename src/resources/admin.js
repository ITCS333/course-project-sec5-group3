let resources = [];
const resourceForm = document.getElementById("resource-form");
const resourcesTbody = document.getElementById("resources-tbody");
let editingId = null;

//نحن في Task1 (Users) أو Resources
function isUsersMode() {
  // إذا فيه password input → هذا Task1
  return document.getElementById("resource-link").type === "password";
}

// ================= TABLE =================
function createResourceRow(resource) {
  const tr = document.createElement("tr");

  const titleTd = document.createElement("td");
  const descriptionTd = document.createElement("td");
  const linkTd = document.createElement("td");

  if (isUsersMode()) {
    // 👇 Users
    titleTd.textContent = resource.name;
    descriptionTd.textContent = resource.email;
    linkTd.textContent = "******";
  } else {
    // Resources (Task4/5)
    titleTd.textContent = resource.title;
    descriptionTd.textContent = resource.description;
    linkTd.textContent = resource.link;
  }

  const actionsTd = document.createElement("td");

  const editBtn = document.createElement("button");
  editBtn.textContent = "Edit";
  editBtn.className = "edit-btn";
  editBtn.dataset.id = resource.id;

  const deleteBtn = document.createElement("button");
  deleteBtn.textContent = "Delete";
  deleteBtn.className = "delete-btn";
  deleteBtn.dataset.id = resource.id;

  actionsTd.appendChild(editBtn);
  actionsTd.appendChild(deleteBtn);

  tr.appendChild(titleTd);
  tr.appendChild(descriptionTd);
  tr.appendChild(linkTd);
  tr.appendChild(actionsTd);

  return tr;
}

function renderTable(arr) {
  const list = arr !== undefined ? arr : resources;
  resourcesTbody.innerHTML = "";
  list.forEach(function(resource) {
    resourcesTbody.appendChild(createResourceRow(resource));
  });
}

// ================= ADD / UPDATE =================
function handleAddResource(event) {
  event.preventDefault();

  const addBtn = document.getElementById("add-resource");

  // 🔥 Users Mode
  if (isUsersMode()) {
    const name = document.getElementById("resource-title").value.trim();
    const email = document.getElementById("resource-description").value.trim();
    const password = document.getElementById("resource-link").value.trim();

    if (editingId !== null) {
      fetch("./api/admin/index.php", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: editingId, name })
      })
      .then(r => r.json())
      .then(result => {
        if (result.success) {
          resources = resources.map(r =>
            r.id === editingId ? { ...r, name } : r
          );
          renderTable();
          resourceForm.reset();
          editingId = null;
          addBtn.textContent = "Add Resource";
        }
      });

    } else {
      fetch("./api/admin/index.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name, email, password })
      })
      .then(r => r.json())
      .then(result => {
        if (result.success) {
          loadAndInitialize(); // 🔥 reload من السيرفر
          resourceForm.reset();
        }
      });
    }

    return;
  }

  // ================= Resources Mode =================
  const title = document.getElementById("resource-title").value.trim();
  const description = document.getElementById("resource-description").value.trim();
  const link = document.getElementById("resource-link").value.trim();

  if (editingId !== null) {
    fetch("./api/index.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: editingId, title, description, link })
    })
    .then(r => r.json())
    .then(result => {
      if (result.success) {
        resources = resources.map(r =>
          r.id === editingId ? { id: editingId, title, description, link } : r
        );
        renderTable();
        resourceForm.reset();
        editingId = null;
        addBtn.textContent = "Add Resource";
      }
    });

  } else {
    fetch("./api/index.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ title, description, link })
    })
    .then(r => r.json())
    .then(result => {
      if (result.success) {
        resources.push({ id: result.id, title, description, link });
        renderTable();
        resourceForm.reset();
      }
    });
  }
}

// ================= TABLE CLICK =================
function handleTableClick(event) {
  const target = event.target;
  const id = parseInt(target.dataset.id);

  if (target.classList.contains("delete-btn")) {

    const url = isUsersMode()
      ? "./api/admin/index.php?id=" + id
      : "./api/index.php?id=" + id;

    fetch(url, { method: "DELETE" })
      .then(r => r.json())
      .then(result => {
        if (result.success) {
          resources = resources.filter(r => r.id !== id);
          renderTable();
        }
      });
  }

  if (target.classList.contains("edit-btn")) {
    const resource = resources.find(r => r.id === id);
    if (!resource) return;

    if (isUsersMode()) {
      document.getElementById("resource-title").value = resource.name;
      document.getElementById("resource-description").value = resource.email;
      document.getElementById("resource-link").value = "";
    } else {
      document.getElementById("resource-title").value = resource.title;
      document.getElementById("resource-description").value = resource.description;
      document.getElementById("resource-link").value = resource.link;
    }

    editingId = id;
    document.getElementById("add-resource").textContent = "Update Resource";
  }
}

// ================= INIT =================
async function loadAndInitialize() {

  const url = isUsersMode()
    ? "./api/admin/index.php?users=1"
    : "./api/index.php";

  const response = await fetch(url);
  const result = await response.json();

  resources = result.data || [];
  renderTable();

  resourceForm.addEventListener("submit", handleAddResource);
  resourcesTbody.addEventListener("click", handleTableClick);
}

loadAndInitialize();
