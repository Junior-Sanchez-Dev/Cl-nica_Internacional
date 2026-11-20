document.addEventListener('DOMContentLoaded', () => {
 const toggle = document.getElementById('menu-toggle'), nav = document.getElementById('main-nav');
 function closeMenu() { nav.classList.remove('open'); toggle.setAttribute('aria-expanded','false'); toggle.setAttribute('aria-label','Abrir menú'); }
 toggle.addEventListener('click',()=>{ const open=nav.classList.toggle('open'); toggle.setAttribute('aria-expanded',String(open)); toggle.setAttribute('aria-label',open?'Cerrar menú':'Abrir menú'); });
 nav.querySelectorAll('a').forEach(a=>a.addEventListener('click',closeMenu));
 document.addEventListener('keydown',e=>{if(e.key==='Escape')closeMenu();});
 const scope=document.getElementById('scope-dialog');
 document.getElementById('scope-open').addEventListener('click',()=>scope.showModal());
 document.getElementById('scope-close').addEventListener('click',()=>scope.close());
});