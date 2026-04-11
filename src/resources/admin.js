let resources = [];

let resourceForm = document.getElementById("resource-form");
const resourcesTbody = document.getElementById("resources-tbody");

function createResourceRow(resource) {
  let tr = document.createElement("tr");

  let titleTd = document.createElement("td");
  titleTd.textContent = resource.title;

  let descriptionTd = document.createElement("td");
  descriptionTd.textContent = resource.description;

  let linkTd = document.createElement("td");
  linkTd.textContent = resource.link;

  let actionsTd = document.createElement("td");

  let editBtn = document.createElement("button");
  editBtn.textContent = "Edit";
  editBtn.className = "edit-btn";
  editBtn.dataset.id = resource.id;

  let deleteBtn = document.createElement("button");
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

function renderTable() {
  resourcesTbody.innerHTML = "";

  for (let i = 0; i < resources.length; i++) {
    let row = createResourceRow(resources[i]);
    resourcesTbody.appendChild(row);
  }
}

async function handleAddResource(event) {
  event.preventDefault();

  let title = document.getElementById("resource-title").value.trim();
  let description = document.getElementById("resource-description").value.trim();
  let link = document.getElementById("resource-link").value.trim();

  let response = await fetch("./api/index.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ title, description, link })
  });

  let result = await response.json();

  if (result.success === true) {
    resources.push({ id: result.id, title, description, link });
    renderTable();
    resourceForm.reset();
  }
}

function handleTableClick(event) {
  let target = event.target;

  if (target.classList.contains("delete-btn")) {
    let id = parseInt(target.dataset.id);

    fetch("./api/index.php?id=" + id, { method: "DELETE" })
      .then(res => res.json())
      .then(result => {
        if (result.success === true) {
          resources = resources.filter(r => r.id !== id);
          renderTable();
        }
      });
  }
}

async function loadAndInitialize() {
  let response = await fetch("./api/index.php");
  let result = await response.json();

  resources = result.data;
  renderTable();

  resourceForm.addEventListener("submit", handleAddResource);
  resourcesTbody.addEventListener("click", handleTableClick);
}

loadAndInitialize();
