const root=document.documentElement;
function applyTheme(theme,persist=false){
 root.dataset.theme=theme==='dark'?'dark':'light';
 const next=root.dataset.theme==='light'?'dark':'light';
 document.querySelectorAll('[data-theme-toggle]').forEach(button=>{
  button.setAttribute('aria-label',`Switch to ${next} theme`);
  button.title=`Switch to ${next} theme`;
 });
 if(persist){try{localStorage.setItem('chandi.theme',root.dataset.theme);}catch{}}
 document.dispatchEvent(new CustomEvent('chandi:theme'));
}
document.querySelectorAll('[data-theme-toggle]').forEach(button=>button.addEventListener('click',()=>applyTheme(root.dataset.theme==='dark'?'light':'dark',true)));
window.addEventListener('storage',event=>{if(event.key==='chandi.theme'||event.key===null)applyTheme(event.newValue);});
applyTheme(root.dataset.theme);
