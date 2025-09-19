<script>
/* Minimal demo auth + license manager.
   Swappable with Firebase later by keeping the same method names. */
window.Auth = (function(){
  const LS_USERS = 'eyek_users_v1';
  const LS_ME    = 'eyek_me_v1';
  const LS_LICENSE = 'eyek_license_v1';

  const DEMO_CODES = ['1234','5867','0000']; // demo-only; replace with Stripe-bound codes later

  function _users(){ return JSON.parse(localStorage.getItem(LS_USERS) || '[]'); }
  function _saveUsers(list){ localStorage.setItem(LS_USERS, JSON.stringify(list)); }
  function _hash(pw){ return btoa(unescape(encodeURIComponent(pw))).split('').reverse().join(''); } // demo-only

  function register(u){
    const users = _users();
    if(users.some(x=>x.email===u.email)) return {ok:false, error:'Email already registered.'};
    users.push({
      id: crypto.randomUUID(),
      name:u.name, email:u.email, pass:_hash(u.password),
      city:u.city, state:u.state, yob:u.yob, heard:u.heard,
      role:u.role||'guest', createdAt: Date.now()
    });
    _saveUsers(users);
    localStorage.setItem(LS_ME, JSON.stringify({email:u.email}));
    return {ok:true};
  }

  function login(email, pw){
    const users=_users();
    const user=users.find(x=>x.email===email);
    if(!user) return {ok:false, error:'No account found for that email.'};
    if(user.pass!==_hash(pw)) return {ok:false, error:'Incorrect password.'};
    localStorage.setItem(LS_ME, JSON.stringify({email}));
    return {ok:true};
  }

  function logout(){ localStorage.removeItem(LS_ME); }

  function current(){
    try{
      const me=JSON.parse(localStorage.getItem(LS_ME)||'null'); if(!me) return null;
      const user=_users().find(x=>x.email===me.email); return user||null;
    }catch{ return null; }
  }

  function isLicensed(){
    const lic = JSON.parse(localStorage.getItem(LS_LICENSE)||'null');
    return !!lic && !!lic.code;
  }
  function applyAccessCode(code){
    if(!current() || current().role!=='host') return {ok:false, error:'Sign in as a Host first.'};
    if(!DEMO_CODES.includes(code)) return {ok:false, error:'Invalid access code for demo.'};
    localStorage.setItem(LS_LICENSE, JSON.stringify({code, boundTo: current().email, at: Date.now()}));
    return {ok:true};
  }

  function require(role, redirectIfMissing){
    const me=current();
    if(!me){ location.href='/auth/login.html?next='+encodeURIComponent(redirectIfMissing||location.pathname); return false; }
    if(role && me.role!==role){ alert('You do not have permission to view this page.'); location.href='/'; return false; }
    return true;
  }

  return { register, login, logout, current, require, isLicensed, applyAccessCode };
})();
</script>
<!-- FILE: /assets/js/auth.js -->
