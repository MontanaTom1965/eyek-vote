<script type="module">
  import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.4/firebase-app.js";
  import { getDatabase, ref, set, push, update } from "https://www.gstatic.com/firebasejs/10.12.4/firebase-database.js";

  // TODO: paste your real config from Firebase console
  export const firebaseApp = initializeApp({
    apiKey: "…", authDomain: "…", databaseURL: "https://eyek-vote…", projectId: "…", appId: "…"
  });
  export const db = getDatabase(firebaseApp);

  // Simple helpers
  export async function fbSet(path, obj){ return set(ref(db, path), obj); }
  export async function fbPush(path, obj){ return push(ref(db, path), obj); }
  export async function fbUpdate(path, obj){ return update(ref(db, path), obj); }
</script>
