

const resourceListSection = document.getElementById("resource-list-section");

function createResourceArticle(resource) {
  const article     = document.createElement("article");
  const h2          = document.createElement("h2");
  h2.textContent    = resource.title;
  const p           = document.createElement("p");
  p.textContent     = resource.description;
  const link        = document.createElement("a");
  link.href         = "details.html?id=" + resource.id;
  link.textContent  = "View Resource & Discussion";
  article.appendChild(h2);
  article.appendChild(p);
  article.appendChild(link);
  return article;
}

async function loadResources() {
  const response = await fetch("./api/index.php");
  const result   = await response.json();
  resourceListSection.innerHTML = "";
  result.data.forEach(function(resource) {
    resourceListSection.appendChild(createResourceArticle(resource));
  });
}

loadResources();
