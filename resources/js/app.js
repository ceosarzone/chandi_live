import './theme.js';
import Alpine from 'alpinejs';
if (document.documentElement.dataset.livewire !== 'true') { window.Alpine = Alpine; Alpine.start(); }
import './market-dashboard.js';
document.addEventListener('submit',event=>{const form=event.target;if(form.dataset.confirm&&!window.confirm(form.dataset.confirm))event.preventDefault();});
document.addEventListener('click',async event=>{const button=event.target.closest('[data-copy],[data-share-calculator]');if(!button)return;const status=button.closest('.calculator-result')?.querySelector('[data-copy-status]');const text=button.hasAttribute('data-share-calculator')?location.href:button.dataset.copy;try{await navigator.clipboard.writeText(text);if(status)status.textContent=button.hasAttribute('data-share-calculator')?'Shareable URL copied.':'Result copied.';}catch{if(status)status.textContent='Clipboard unavailable. Select and copy the displayed result or address manually.';}});
const offline=document.getElementById('offline-notice');const update=()=>offline.hidden=navigator.onLine;window.addEventListener('online',update);window.addEventListener('offline',update);update();

// Poll only our cached display; external providers are refreshed by the central job.
document.querySelectorAll('[data-live-rates]').forEach(root => {
 let loading = false;
 const content = root.querySelector('[data-rates-content]');
 const status = root.querySelector('[data-rates-refresh-status]');
 async function refreshRates() {
  if (loading || document.hidden || !navigator.onLine || content.contains(document.activeElement)) return;
  loading = true;
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 10000);
  try {
   const response = await fetch(root.dataset.refreshUrl, {signal:controller.signal, headers:{Accept:'text/html'}});
   if (!response.ok) throw new Error('Refresh unavailable');
   const expanded = [...content.querySelectorAll('details')].map(detail => detail.open);
   content.innerHTML = await response.text();
   content.querySelectorAll('details').forEach((detail,index) => { detail.open = expanded[index] || false; });
   status.textContent = 'Display checked at '+new Date().toLocaleTimeString()+' · refreshes every 60 seconds. Source timestamps show market freshness.';
  } catch {
   status.textContent = 'Display refresh unavailable. Last-loaded values retained; check their source timestamps.';
  } finally { clearTimeout(timeout); loading = false; }
 }
 setInterval(refreshRates, 60000);
 document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshRates(); });
});
