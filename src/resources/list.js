let resourceListSection = document.getElementById("resource-list-section");

function createResourceArticle(resource) {
  const article = document.createElement("article");

  const h2 = document.createElement("h2");
  h2.textContent = resource.title;

  const descriptionP = document.createElement("p");
  descriptionP.textContent = resource.description;

  const link = document.createElement("a");
  link.href = "details.html?id=" + resource.id;
  link.textContent = "View Resource & Discussion";

  article.appendChild(h2);
  article.appendChild(descriptionP);
  article.appendChild(link);

  return article;
}

async function loadResources() {
  try {
    const response = await fetch("./api/index.php");
    const result = await response.json();

    if (!result.success) return;

    resourceListSection.innerHTML = "";

    result.data.forEach(function(resource) {
      const article = createResourceArticle(resource);
      resourceListSection.appendChild(article);
    });
  } catch (error) {
    console.error(error);
  }
}

loadResources();
