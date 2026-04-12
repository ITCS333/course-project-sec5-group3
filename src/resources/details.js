

let currentResourceId = null;
let currentComments   = [];

const resourceTitle       = document.getElementById("resource-title");
const resourceDescription = document.getElementById("resource-description");
const resourceLink        = document.getElementById("resource-link");
const commentList         = document.getElementById("comment-list");
const commentForm         = document.getElementById("comment-form");
const newCommentInput     = document.getElementById("new-comment");

function getResourceIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get("id");
}

function renderResourceDetails(resource) {
  resourceTitle.textContent       = resource.title;
  resourceDescription.textContent = resource.description;
  resourceLink.href               = resource.link;
}

function createCommentArticle(comment) {
  const article = document.createElement("article");
  const p       = document.createElement("p");
  p.textContent = comment.text;
  const footer  = document.createElement("footer");
  footer.textContent = "Posted by: " + comment.author;
  article.appendChild(p);
  article.appendChild(footer);
  return article;
}

function renderComments() {
  commentList.innerHTML = "";
  currentComments.forEach(function(comment) {
    commentList.appendChild(createCommentArticle(comment));
  });
}

async function handleAddComment(event) {
  event.preventDefault();
  const commentText = newCommentInput.value.trim();
  if (!commentText) return;
  const response = await fetch("./api/index.php?action=comment", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      resource_id: currentResourceId,
      author: "Student",
      text: commentText
    })
  });
  const result = await response.json();
  if (result.success) {
    currentComments.push(result.data);
    renderComments();
    newCommentInput.value = "";
  }
}

async function initializePage() {
  currentResourceId = getResourceIdFromURL();
  if (!currentResourceId) {
    resourceTitle.textContent = "Resource not found.";
    return;
  }
  const [resourceResponse, commentsResponse] = await Promise.all([
    fetch("./api/index.php?id=" + currentResourceId),
    fetch("./api/index.php?resource_id=" + currentResourceId + "&action=comments")
  ]);
  const resourceResult = await resourceResponse.json();
  const commentsResult = await commentsResponse.json();
  currentComments = commentsResult.success ? commentsResult.data : [];
  if (resourceResult.success && resourceResult.data) {
    renderResourceDetails(resourceResult.data);
    renderComments();
    commentForm.addEventListener("submit", handleAddComment);
  } else {
    resourceTitle.textContent = "Resource not found.";
  }
}

initializePage();
