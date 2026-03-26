let eventsData = [];
let postsData = [];

const state = {
  category: "all",
  department: "all",
  sort: "latest"
};

const eventsGrid = document.getElementById("eventsGrid");
const postsGrid = document.getElementById("postsGrid");
const eventModal = document.getElementById("eventModal");
const postModal = document.getElementById("postModal");
const eventSubmitModal = document.getElementById("eventSubmitModal");
const eventModalContent = document.getElementById("eventModalContent");
const API_BASE = "./backend/portal_api.php";

function formatDate(dateStr) {
  if (!dateStr) return "Date will be announced";
  const date = new Date(dateStr);
  if (Number.isNaN(date.getTime())) return "Date will be announced";
  return date.toLocaleString([], {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit"
  });
}

function humanTime(dateStr) {
  if (!dateStr) return "Just now";
  const time = new Date(dateStr);
  if (Number.isNaN(time.getTime())) return "Just now";
  const diff = Math.floor((Date.now() - time.getTime()) / 1000);
  if (diff < 60) return "Just now";
  if (diff < 3600) return `${Math.floor(diff / 60)} min ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)} hr ago`;
  return `${Math.floor(diff / 86400)} day ago`;
}

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function renderEvents() {
  const filtered = eventsData
    .filter((ev) => state.category === "all" || ev.category === state.category)
    .filter((ev) => state.department === "all" || ev.department === state.department)
    .sort((a, b) => (
      state.sort === "latest"
        ? new Date(b.datetime) - new Date(a.datetime)
        : new Date(a.datetime) - new Date(b.datetime)
    ));

  eventsGrid.innerHTML = filtered.map((ev) => `
    <article class="event-card reveal-card">
      <div class="event-thumb" style="${ev.thumb ? `background-image:linear-gradient(120deg, rgba(9,26,58,.65), rgba(9,26,58,.45)),url('${escapeHtml(ev.thumb)}');` : "background:linear-gradient(120deg,#8f2dc5,#db5db4);"}">
        <span>${ev.status === "upcoming" ? "Upcoming Event" : "Past Event"}</span>
      </div>
      <div class="event-body">
        <span class="badge ${escapeHtml(ev.category)}">${escapeHtml(ev.category)}</span>
        <h3>${escapeHtml(ev.title)}</h3>
        <p class="meta">${formatDate(ev.datetime)}</p>
        <p class="meta">${escapeHtml(ev.location || "Campus Venue")}</p>
        <p class="meta">Dept: ${escapeHtml((ev.department || "cse").toUpperCase())}</p>
        <button class="btn ripple view-detail" data-event-id="${ev.id}">View Details</button>
      </div>
    </article>
  `).join("");

  if (!filtered.length) {
    eventsGrid.innerHTML = "<p>No events match this filter yet.</p>";
  }
}

function initials(name) {
  return name.split(" ").map((p) => p[0]).slice(0, 2).join("").toUpperCase();
}

function renderPosts() {
  postsGrid.innerHTML = postsData.map((post, i) => `
    <article class="post-card reveal-post" style="animation-delay:${i * 90}ms">
      <div class="post-head">
        <span class="avatar">${initials(post.author || "NA")}</span>
        <div>
          <strong>${escapeHtml(post.author)}</strong>
          <div class="post-meta">${escapeHtml(post.meta)} - ${humanTime(post.time)}</div>
        </div>
        <button class="like-btn" data-post-id="${post.id}" aria-label="Like post">❤</button>
      </div>
      <p>${escapeHtml(post.content)}</p>
      <small class="like-count" data-like-count-id="${post.id}">${post.likes} likes</small>
    </article>
  `).join("");
  observePostCards();
}

function openModal(modalId) {
  document.getElementById(modalId).classList.add("open");
}

function closeModal(modalId) {
  document.getElementById(modalId).classList.remove("open");
}

function attachEvents() {
  document.querySelectorAll(".pill").forEach((btn) => {
    btn.addEventListener("click", () => {
      const group = btn.dataset.filterType;
      btn.parentElement.querySelectorAll(".pill").forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      state[group] = btn.dataset.filter;
      renderEvents();
    });
  });

  document.getElementById("dateSort").addEventListener("change", (e) => {
    state.sort = e.target.value;
    renderEvents();
  });

  document.addEventListener("click", (e) => {
    if (e.target.classList.contains("view-detail")) {
      const eventId = Number(e.target.dataset.eventId);
      const event = eventsData.find((ev) => ev.id === eventId);
      if (!event) return;
      eventModalContent.innerHTML = `
        <h3>${escapeHtml(event.title)}</h3>
        <p><strong>Date & Time:</strong> ${formatDate(event.datetime)}</p>
        <p><strong>Location:</strong> ${escapeHtml(event.location || "Campus Venue")}</p>
        <p><strong>Category:</strong> ${escapeHtml(event.category)}</p>
        <p><strong>Department:</strong> ${escapeHtml((event.department || "cse").toUpperCase())}</p>
        <p>${escapeHtml(event.details || "Details will be updated soon.")}</p>
      `;
      openModal("eventModal");
    }
  });

  document.querySelectorAll("[data-close-modal]").forEach((btn) => {
    btn.addEventListener("click", () => closeModal(btn.dataset.closeModal));
  });

  [eventModal, postModal, eventSubmitModal].forEach((modal) => {
    modal.addEventListener("click", (e) => {
      if (e.target === modal) modal.classList.remove("open");
    });
  });

  document.getElementById("newPostBtn").addEventListener("click", () => openModal("postModal"));
  document.getElementById("newEventBtn").addEventListener("click", () => openModal("eventSubmitModal"));

  document.getElementById("postForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const author = document.getElementById("postAuthor").value.trim();
    const meta = document.getElementById("postMeta").value.trim();
    const content = document.getElementById("postContent").value.trim();
    if (!author || !meta || !content) return;
    if (author.length > 120 || meta.length > 180 || content.length > 2000) {
      alert("Please keep text within allowed length.");
      return;
    }
    try {
      const res = await fetch(`${API_BASE}?action=posts`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          author,
          meta,
          content,
          csrf: window.PORTAL_CSRF_TOKEN || ""
        })
      });
      const payload = await res.json();
      if (!res.ok || !payload.ok) {
        alert(payload.message || "Failed to publish post");
        return;
      }
      alert("Post submitted and is pending admin approval.");
      e.target.reset();
      closeModal("postModal");
      await loadPosts();
    } catch (error) {
      alert("Network error while publishing post.");
    }
  });

  document.getElementById("eventForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const title = document.getElementById("eventTitle").value.trim();
    const datetime = document.getElementById("eventDateTime").value;
    const location = document.getElementById("eventLocation").value.trim();
    const category = document.getElementById("eventCategory").value;
    const department = document.getElementById("eventDepartment").value;
    const details = document.getElementById("eventDetails").value.trim();
    const fee = Number(document.getElementById("eventFee").value);
    const teamSize = Number(document.getElementById("eventTeamSize").value);

    if (!title || !datetime || !location || !category || !department || !details) {
      alert("Please fill all event fields.");
      return;
    }
    if (title.length > 180 || location.length > 255 || details.length > 3000) {
      alert("Event text is too long.");
      return;
    }
    if (Number.isNaN(fee) || fee < 0 || Number.isNaN(teamSize) || teamSize < 1 || teamSize > 100) {
      alert("Please enter valid fee and team size.");
      return;
    }

    try {
      const res = await fetch(`${API_BASE}?action=events`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          title,
          datetime: datetime.replace("T", " "),
          location,
          category,
          department,
          details,
          fee,
          team_size: teamSize,
          csrf: window.PORTAL_CSRF_TOKEN || ""
        })
      });
      const payload = await res.json();
      if (!res.ok || !payload.ok) {
        alert(payload.message || "Failed to submit event");
        return;
      }
      alert("Event submitted and is pending admin approval.");
      e.target.reset();
      closeModal("eventSubmitModal");
      await loadEvents();
    } catch (error) {
      alert("Network error while submitting event.");
    }
  });

  postsGrid.addEventListener("click", async (e) => {
    if (!e.target.classList.contains("like-btn")) return;
    const likeBtn = e.target;
    const postId = Number(likeBtn.dataset.postId);
    const likedKey = `portal-liked-${postId}`;
    if (localStorage.getItem(likedKey)) {
      alert("You already liked this post on this device.");
      return;
    }

    try {
      const res = await fetch(`${API_BASE}?action=like_post`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          post_id: postId,
          csrf: window.PORTAL_CSRF_TOKEN || ""
        })
      });
      const payload = await res.json();
      if (!res.ok || !payload.ok) {
        alert(payload.message || "Failed to like post");
        return;
      }
      const likeCount = postsGrid.querySelector(`[data-like-count-id="${postId}"]`);
      if (likeCount) {
        likeCount.textContent = `${payload.likes} likes`;
      }
      localStorage.setItem(likedKey, "1");
    } catch (error) {
      alert("Network error while liking post.");
      return;
    }

    likeBtn.classList.remove("liked");
    void likeBtn.offsetWidth;
    likeBtn.classList.add("liked");
    likeBtn.style.color = likeBtn.style.color ? "" : "#e14874";
  });

  document.getElementById("menuToggle").addEventListener("click", () => {
    document.querySelector(".nav-links").classList.toggle("open");
  });
}

function observePostCards() {
  const cards = document.querySelectorAll(".reveal-post");
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("show");
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.14 });

  cards.forEach((card) => observer.observe(card));
}

async function loadEvents() {
  try {
    const res = await fetch(`${API_BASE}?action=events`);
    const payload = await res.json();
    if (!res.ok || !payload.ok) throw new Error("Unable to load events");
    eventsData = payload.events || [];
  } catch (error) {
    eventsData = [];
  }
  renderEvents();
}

async function loadPosts() {
  try {
    const res = await fetch(`${API_BASE}?action=posts`);
    const payload = await res.json();
    if (!res.ok || !payload.ok) throw new Error("Unable to load posts");
    postsData = payload.posts || [];
  } catch (error) {
    postsData = [];
  }
  renderPosts();
}

loadEvents();
loadPosts();
attachEvents();
