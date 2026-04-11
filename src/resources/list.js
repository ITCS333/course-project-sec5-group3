let resourceListSection = document.getElementById("resource-list-section");

function createResourceArticle(resource) {
  let article = document.createElement("article");

  let title = document.createElement("h2");
  title.textContent = resource.title;

  let description = document.createElement("p");
  description.textContent = resource.description;

  let link = document.createElement("a");
  link.href = "details.html?id=" + resource.id;
  link.textContent = "View Resource & Discussion";

  article.appendChild(title);
  article.appendChild(description);
  article.appendChild(link);

  return article;
}

async function loadResources() {
  try {
    let response = await fetch("./api/index.php");
    let result = await response.json();

    if (result.success === true) {
      resourceListSection.innerHTML = "";

      for (let i = 0; i < result.data.length; i++) {
        let article = createResourceArticle(result.data[i]);
        resourceListSection.appendChild(article);
      }
    }
  } catch (error) {
    console.log(error);
  }
}

loadResources();
