import {historyAxis} from './chart-axis.js';
let ChartClass;
const labels = {USD:'International silver spot', PKR:'Pakistan · converted silver spot', INR:'India · converted silver spot', PSL:'Pakistan · published PSL quote'};
const units = {oz:'troy ounce',tola:'tola',g:'gram','10g':'10 grams',kg:'kilogram'};
const defaults = {USD:'oz',PKR:'tola',INR:'kg',PSL:'tola'};
const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const format = value => Number(value).toLocaleString('en-US',{minimumFractionDigits:2, maximumFractionDigits:2});
const date = at => new Date(at).toLocaleDateString('en-GB',{timeZone:'UTC',day:'numeric',month:'short',year:'numeric'});
document.querySelectorAll('[data-market-chart]').forEach(root => {
 let chart, controller, period='30d', currency=root.dataset.selected || 'USD', unit=defaults[currency];
 const find = selector => root.querySelector(selector);
 const empty=find('[data-chart-empty]'), picker=find('[data-chart-unit-select]');
 const palette=()=>{const style=getComputedStyle(document.documentElement);return Object.fromEntries(['reference','recorded','grid','tick','fill'].map(key=>[key,style.getPropertyValue('--chart-'+key).trim()]));};
 const chartFill=(ctx,colors)=>{const gradient=ctx.createLinearGradient(0,0,0,350);gradient.addColorStop(0,colors.fill);gradient.addColorStop(1,'transparent');return gradient;};
 document.addEventListener('chandi:theme',()=>{
  if(!chart)return;
  const colors=palette();
  chart.data.datasets.forEach((dataset,index)=>{dataset.borderColor=index===0?colors.reference:colors.recorded;dataset.backgroundColor=index===0?chartFill(chart.ctx,colors):'transparent';});
  chart.options.scales.x.ticks.color=colors.tick;chart.options.scales.y.ticks.color=colors.tick;chart.options.scales.y.grid.color=colors.grid;
  chart.update('none');
 });
 async function draw() {
  controller?.abort(); const pending = new AbortController(); controller=pending;
  const timeout = setTimeout(()=>pending.abort('timeout'),15000);
  root.setAttribute('aria-busy','true');
  picker.value=unit; picker.disabled=currency==='PSL';
  find('[data-chart-title]').textContent=labels[currency];
  root.querySelectorAll('[data-currency]').forEach(b=>{b.classList.toggle('active',b.dataset.currency===currency);b.setAttribute('aria-pressed',String(b.dataset.currency===currency));});
  root.querySelectorAll('[data-period]').forEach(b=>{b.classList.toggle('active',b.dataset.period===period);b.setAttribute('aria-pressed',String(b.dataset.period===period));});
  try {
   const query=new URLSearchParams({period,currency,unit});
   const response=await fetch(document.documentElement.dataset.baseUrl.replace(/\/$/,'')+'/market/chart?'+query,{signal:pending.signal,headers:{Accept:'application/json'}});
   if(!response.ok)throw new Error('History unavailable');
   const data=await response.json(); if(pending.signal.aborted)return;
   const points=data.points, available=points.filter(p=>p.value!==null);
   const windowNames={'24h':'Last 24 hours','7d':'Last 7 days','30d':'Last 30 days','90d':'Last 90 days','1y':'Last 12 months',max:'All available history'};
   find('[data-chart-range]').textContent=windowNames[period]+(available.length?' · '+date(available[0].at)+' – '+date(available.at(-1).at):' · no observations');
   const suffix=data.currency+' / '+(currency==='PSL'?'published quote':units[unit]);
   if(chart){chart.destroy();chart=null;}
   empty.hidden=available.length>=2;
   find('canvas').hidden=available.length<2;
   find('[data-chart-value]').textContent=available.length?data.currency+' '+format(available.at(-1).value):'—';
   find('[data-chart-unit]').textContent=suffix+' · latest plotted value';
   find('[data-chart-column]').textContent=suffix;
   const change=find('[data-chart-change]');
   change.className=data.change?(Number(data.change.percent)>=0?'change-positive':'change-negative'):'';
   change.textContent=data.change?`${Number(data.change.percent)>=0?'+':''}${Number(data.change.percent).toFixed(2)}% over available period`:'Comparison unavailable';
   find('[data-chart-low]').textContent=data.low===null?'—':format(data.low);
   find('[data-chart-high]').textContent=data.high===null?'—':format(data.high);
   find('[data-chart-count]').textContent=available.length.toLocaleString();
   find('[data-chart-coverage]').textContent=available.length?date(available[0].at)+' – '+date(available.at(-1).at):'No observations';
   find('[data-chart-note]').textContent=(data.state==='demo'?'DEMO · simulated observations. ':'')+data.note;
   if(available.length>=2){
    if(!ChartClass)ChartClass=(await import('./chart-engine.js')).Chart;
    if(pending.signal.aborted)return;
    const ctx=find('canvas').getContext('2d');
    const colors=palette(), gradient=chartFill(ctx,colors);
    const axis=historyAxis(period,available[0].at,available.at(-1).at);
    const datasets=['reference','recorded'].map(series=>{
     const plot=[];
     points.forEach((p,i)=>{
      // Explicit breaks expose missing daily dates; null is never a fabricated price.
      if(i && Date.parse(p.at)-Date.parse(points[i-1].at)>36*3600000)plot.push({x:Date.parse(points[i-1].at)+1,y:null});
      plot.push({x:Date.parse(p.at),y:p.series===series && p.value!==null?Number(p.value):null,source:p.source,resolution:p.resolution});
     });
     return {label:series==='reference'?'Daily reference':'Recorded quote',data:plot,borderColor:series==='reference'?colors.reference:colors.recorded,backgroundColor:series==='reference'?gradient:'transparent',fill:series==='reference',borderWidth:2,pointRadius:series==='recorded'?2:0,pointHoverRadius:5,tension:0,spanGaps:false};
    });
    chart=new ChartClass(ctx,{type:'line',data:{datasets},options:{responsive:true,maintainAspectRatio:false,animation:reduced?false:{duration:200},interaction:{mode:'nearest',intersect:false},plugins:{legend:{display:false},tooltip:{displayColors:false,callbacks:{title:items=>items[0].raw.resolution==='daily'?date(items[0].parsed.x)+' · daily reference':new Date(items[0].parsed.x).toLocaleString('en-GB',{timeZone:'UTC'})+' UTC',label:context=>suffix+': '+format(context.parsed.y),afterLabel:context=>context.raw.source}}},scales:{x:{type:'linear',min:axis.min,max:axis.max,afterBuildTicks:axis.afterBuildTicks,grid:{display:false},border:{display:false},ticks:{color:colors.tick,maxRotation:0,maxTicksLimit:6,font:{size:11},callback:axis.label}},y:{position:'right',border:{display:false},grid:{color:colors.grid},ticks:{color:colors.tick,font:{size:11},maxTicksLimit:6,callback:value=>Number(value).toLocaleString('en-US',{maximumFractionDigits:2})}}}}});
   } else {
    empty.querySelector('h3').textContent=available.length?'First observation recorded.':'No observations in this period.';
    empty.querySelector('p').textContent=currency==='PSL'?'PSL history starts with collected source updates. Earlier PSL history is not available.':period==='24h'?'Intraday history grows as live observations arrive. Select 30D or 1Y for daily history.':'Choose another period to explore the available history.';
   }
   const tbody=find('[data-chart-table]'); tbody.replaceChildren();
   for(const point of points){const tr=document.createElement('tr'); for(const text of [point.resolution==='daily'?point.at.slice(0,10)+' · daily UTC':new Date(point.at).toLocaleString('en-GB',{timeZone:'UTC'})+' UTC',point.value===null?'Unavailable':format(point.value),point.source]){const td=document.createElement('td');td.textContent=text;tr.append(td);}tbody.append(tr);}
  } catch(error){
   if(controller!==pending)return;
   if(chart){chart.destroy();chart=null;} find('canvas').hidden=true;
   empty.hidden=false;empty.querySelector('h3').textContent='History is temporarily unavailable.';empty.querySelector('p').textContent='Select a period to retry.';
   for(const selector of ['value','low','high','count','coverage'])find(`[data-chart-${selector}]`).textContent='—';
   find('[data-chart-range]').textContent='Selected period unavailable';
   find('[data-chart-change]').textContent='Comparison unavailable';find('[data-chart-note]').textContent='Unable to load the selected history. Please retry.';
   find('[data-chart-table]').replaceChildren();
  } finally {clearTimeout(timeout);if(controller===pending)root.removeAttribute('aria-busy');}
 }
 function selectMarket(next){currency=next;unit=defaults[next];draw();}
 root.querySelectorAll('[data-currency]').forEach(button=>button.addEventListener('click',()=>selectMarket(button.dataset.currency)));
 root.querySelectorAll('[data-period]').forEach(button=>button.addEventListener('click',()=>{period=button.dataset.period;draw();}));
 picker.addEventListener('change',()=>{unit=picker.value;draw();});
 document.addEventListener('click',event=>{const link=event.target.closest('[data-select-market]');if(link)selectMarket(link.dataset.selectMarket);});
 setInterval(()=>{if(!document.hidden && navigator.onLine && !root.contains(document.activeElement))draw();},60000);
 draw();
});
