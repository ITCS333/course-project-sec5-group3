/*
  Requirement: Make the "Manage Resources" page interactive.

  Instructions:
  1. Link this file to `admin.html` using:
     <script src="admin.js" defer></script>
  
  2. In `admin.html`, add id="resources-tbody" to the <tbody> element
     inside your resources-table. This id is required by this script.
  
  3. Implement the TODOs below.
*/

// --- Global Data Store ---
// This will hold the resources loaded from the API.
let resources = [];

// --- Element Selections ---
// TODO: Select the resource form ('#resource-form').
const resourceForm = document.getElementById("resource-form");

// TODO: Select the resources table body ('#resources-tbody').
const resourcesTbody = document.getElementById("resources-tbody");

// --- Functions ---

/**
 * TODO: Implement the createResourceRow function.
 * It takes one resource object { id, title, description, link }.
 * It should return a <tr> element with the following <td>s:
 * 1. A <td> for the title.
 * 2. A <td> for the description.
 * 3. A <td> for the link.
 * 4. A <td> containing two buttons:
 *    - An "Edit" button with class="edit-btn" and data-id="${id}".
 *    - A "Delete" button with class="delete-btn" and data-id="${id}".
 */
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

/**
 * TODO: Implement the renderTable function.
 * It should:
 * 1. Clear the resources table body ('#resources-tbody').
 * 2. Loop through the global `resources` array.
 * 3. For each resource, call `createResourceRow()` and
 *    append the returned <tr> to the table body.
 */
function renderTable() {
  resourcesTbody.innerHTML = "";

  resources.forEach(function(resource) {
    const row = createResourceRow(resource);
    resourcesTbody.appendChild(row);
  });
}

/**
 * TODO: Implement the handleAddResource function.
 * This is the event handler for the form's 'submit' event.
 * It should:
 * 1. Prevent the form's default submission.
 * 2. Get the values from the title (id="resource-title"),
 *    description (id="resource-description"), and
 *    link (id="resource-link") inputs.
 * 3. Use `fetch()` to POST the new resource to the API:
 *    - URL: './api/index.php'
 *    - Method: POST
 *    - Headers: { 'Content-Type': 'application/json' }
 *    - Body: JSON.stringify({ title, description, link })
 * 4. The API returns { success: true, id: <new id> }.
 *    Add the new resource object (including the id returned by the API)
 *    to the global `resources` array.
 * 5. Call `renderTable()` to refresh the list.
 * 6. Reset the form.
 */
async function handleAddResource(event) {
  event.preventDefault();

  const title       = document.getElementById("resource-title").value.trim();
  const description = document.getElementById("resource-description").value.trim();
  const link        = document.getElementById("resource-link").value.trim();

  try {
    const response = await fetch("./api/index.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ title, description, link })
    });

    const result = await response.json();

    if (result.success) {
      resources.push({ id: result.id, title, description, link });
      renderTable();
      resourceForm.reset();
    } else {
      alert(result.message || "An error occurred.");
    }
  } catch (error) {
    alert("An error occurred: " + error.message);
  }
}

/**
 * TODO: Implement the handleTableClick function.
 * This handles click events on the table body using event delegation.
 * It should:
 *
 * If the clicked element has class "delete-btn":
 * 1. Get the resource id from the button's data-id attribute.
 * 2. Use `fetch()` to DELETE the resource via the API:
 *    - URL: `./api/index.php?id=${id}`
 *    - Method: DELETE
 * 3. On success, remove the resource from the global `resources` array
 *    by filtering out the entry with the matching id.
 * 4. Call `renderTable()` to refresh the list.
 *
 * If the clicked element has class "edit-btn":
 * 1. Get the resource id from the button's data-id attribute.
 * 2. Find the matching resource in the global `resources` array.
 * 3. Populate the form fields (id="resource-title", id="resource-description",
 *    id="resource-link") with the resource's current values so the admin
 *    can edit them.
 * 4. Change the submit button (id="add-resource") text to "Update Resource"
 *    to indicate edit mode.
 * 5. On form submit, use `fetch()` to PUT the updated resource to the API:
 *    - URL: './api/index.php'
 *    - Method: PUT
 *    - Headers: { 'Content-Type': 'application/json' }
 *    - Body: JSON.stringify({ id, title, description, link })
 * 6. On success, update the matching resource in the global `resources` array.
 * 7. Call `renderTable()` and reset the form back to "Add" mode,
 *    restoring the submit button text to "Add Resource".
 */
function handleTableClick(event) {
  const target = event.target;

  if (target.classList.contains("delete-btn")) {
    const id = parseInt(target.dataset.id);

    fetch("./api/index.php?id=" + id, {
      method: "DELETE"
    })
      .then(function(response) { return response.json(); })
      .then(function(result) {
        if (result.success) {
          resources = resources.filter(function(r) { return r.id !== id; });
          renderTable();
        } else {
          alert(result.message || "An error occurred.");
        }
      })
      .catch(function(error) {
        alert("An error occurred: " + error.message);
      });
  }

  if (target.classList.contains("edit-btn")) {
    const id = parseInt(target.dataset.id);

    const resource = resources.find(function(r) { return r.id === id; });
    if (!resource) return;

    document.getElementById("resource-title").value       = resource.title;
    document.getElementById("resource-description").value = resource.description;
    document.getElementById("resource-link").value        = resource.link;

    const addBtn = document.getElementById("add-resource");
    addBtn.textContent = "Update Resource";

    const newForm = resourceForm.cloneNode(true);
    resourceForm.parentNode.replaceChild(newForm, resourceForm);

    newForm.addEventListener("submit", async function handleUpdate(event) {
      event.preventDefault();

      const title       = document.getElementById("resource-title").value.trim();
      const description = document.getElementById("resource-description").value.trim();
      const link        = document.getElementById("resource-link").value.trim();

      try {
        const response = await fetch("./api/index.php", {
          method: "PUT",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ id, title, description, link })
        });

        const result = await response.json();

        if (result.success) {
          resources = resources.map(function(r) {
            return r.id === id ? { id, title, description, link } : r;
          });

          renderTable();
          newForm.reset();

          document.getElementById("add-resource").textContent = "Add Resource";

          newForm.removeEventListener("submit", handleUpdate);
          newForm.addEventListener("submit", handleAddResource);
        } else {
          alert(result.message || "An error occurred.");
        }
      } catch (error) {
        alert("An error occurred: " + error.message);
      }
    });
  }
}

/**
 * TODO: Implement the loadAndInitialize function.
 * This function must be 'async'.
 * It should:
 * 1. Use `fetch()` to GET all resources from the API:
 *    - URL: './api/index.php'
 *    - The API returns { success: true, data: [...] }
 * 2. Store the resources array (from `data`) in the global `resources` variable.
 * 3. Call `renderTable()` to populate the table for the first time.
 * 4. Add the 'submit' event listener to the resource form (id="resource-form"),
 *    calling `handleAddResource`.
 * 5. Add the 'click' event listener to the table body (id="resources-tbody"),
 *    calling `handleTableClick`.
 */
async function loadAndInitialize() {
  try {
    const response = await fetch("./api/index.php");

    if (!response.ok) {
      console.error("Failed to fetch resources:", response.statusText);
      alert("Failed to load resources from the server.");
      return;
    }

    const result = await response.json();
    resources = result.data;
    renderTable();

    resourceForm.addEventListener("submit", handleAddResource);
    resourcesTbody.addEventListener("click", handleTableClick);
  } catch (error) {
    console.error("Error:", error);
    alert("An error occurred while loading resources: " + error.message);
  }
}

// --- Initial Page Load ---
// Call the main async function to start the application.
loadAndInitialize();
