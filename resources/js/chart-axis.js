// Keep the plot inside its observations; timestamp rounding must never add future months.
export function historyAxis(period, from, to) {
 const min=Date.parse(from), max=Date.parse(to);
 const calendarMonths=(period==='1y'||period==='max') && max-min>90*86400000;
 const options={
  min,max,
  label:value=>new Date(value).toLocaleString('en-GB',{
   timeZone:'UTC',
   ...(period==='24h'?{hour:'2-digit',minute:'2-digit'}:calendarMonths?{month:'short',year:'numeric'}:{day:'numeric',month:'short',...(new Date(min).getUTCFullYear()!==new Date(max).getUTCFullYear()?{year:'numeric'}:{})}),
  }),
 };
 if(calendarMonths){
  const first=new Date(min), last=new Date(max), ticks=[min];
  // Use real calendar boundaries between the first and last observed months.
  // Endpoints retain their true dates, rather than extending to a rounded month.
  const endMonth=Date.UTC(last.getUTCFullYear(),last.getUTCMonth(),1);
  for(let month=Date.UTC(first.getUTCFullYear(),first.getUTCMonth()+1,1);month<endMonth;){
   ticks.push(month);
   const current=new Date(month);month=Date.UTC(current.getUTCFullYear(),current.getUTCMonth()+1,1);
  }
  ticks.push(max);
  options.afterBuildTicks=scale=>{scale.ticks=ticks.map(value=>({value}));};
 }
 return options;
}
