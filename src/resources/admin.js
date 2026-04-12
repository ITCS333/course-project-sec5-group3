let resources = [];
const resourceForm = document.getElementById("resource-form");
const resourcesTbody = document.getElementById("resources-tbody");
let editingId = null;

function createResourceRow(resource) {
  const tr = document.createElement("tr");
  const titleTd = document.createElement("td");
  titleTd.textContent = resource.title;
  const descriptionTd = document.createElement("td");
  descriptionTd.textContent = resource.description;
  const linkTd = document.createElement("td");
  linkTd.textContent = resource.link;
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

function handleAddResource(event) {
  event.preventDefault();
  const title       = document.getElementById("resource-title").value.trim();
  const description = document.getElementById("resource-description").value.trim();
  const link        = document.getElementById("resource-link").value.trim();
  const addBtn      = document.getElementById("add-resource");

  if (editingId !== null) {
    fetch("./api/index.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: editingId, title, description, link })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
      if (result.success) {
        resources = resources.map(function(r) {
          return r.id === editingId ? { id: editingId, title, description, link } : r;
        });
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
    .then(function(r) { return r.json(); })
    .then(function(result) {
      if (result.success) {
        resources.push({ id: result.id, title, description, link });
        renderTable();
        resourceForm.reset();
      }
    });
  }
}

function handleTableClick(event) {
  const target = event.target;

  if (target.classList.contains("delete-btn")) {
    const id = parseInt(target.dataset.id);
    fetch("./api/index.php?id=" + id, { method: "DELETE" })
      .then(function(r) { return r.json(); })
      .then(function(result) {
        if (result.success) {
          resources = resources.filter(function(r) { return r.id !== id; });
          renderTable();
        }
      });
  }

  if (target.classList.contains("edit-btn")) {
    const id = parseInt(target.dataset.id);
    const resource = resources.find(function(r) { return r.id === id; });
    if (!resource) return;
    document.getElementById("resource-title").value       = resource.title;
    document.getElementById("resource-description").value = resource.description;
    document.getElementById("resource-link").value        = resource.link;
    editingId = id;
    document.getElementById("add-resource").textContent = "Update Resource";
  }
}

async function loadAndInitialize() {
  const response = await fetch("./api/index.php");
  const result   = await response.json();
  resources = result.data;
  renderTable();
  resourceForm.addEventListener("submit", handleAddResource);
  resourcesTbody.addEventListener("click", handleTableClick);
}

loadAndInitialize();
