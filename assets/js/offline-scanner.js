window.OfflineScanner = (() => {
 const owner = document.querySelector('meta[name="scan-owner"]')?.content;
 const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
 let syncing=false;
 const ready = new Promise((resolve,reject) => {
  if (!owner || !window.indexedDB) return resolve(null);
  const request=indexedDB.open('agpms-scans-v3-'+owner,1);
  request.onupgradeneeded=()=>request.result.createObjectStore('events',{keyPath:'event_id'});
  request.onsuccess=()=>resolve(request.result);request.onerror=()=>reject(request.error);
 });
 const uuid=()=>crypto.randomUUID();
 async function getQueueCount() {
  const db=await ready;if(!db)return 0;
  return new Promise((resolve,reject)=>{const r=db.transaction('events').objectStore('events').count();r.onsuccess=()=>resolve(r.result);r.onerror=()=>reject(r.error);});
 }
 async function queue(token,action='checkin') {
  const db=await ready;if(!db)throw new Error('Offline storage unavailable.');
  const event={event_id:uuid(),token,action,scanned_at:new Date().toISOString()};
  await new Promise((resolve,reject)=>{const tx=db.transaction('events','readwrite');tx.objectStore('events').add(event);tx.oncomplete=resolve;tx.onerror=()=>reject(tx.error);});
  return event;
 }
 async function sync() {
  if(syncing || !navigator.onLine)return;syncing=true;
  try {
   const db=await ready;if(!db)return;
   const items=await new Promise((resolve,reject)=>{const r=db.transaction('events').objectStore('events').getAll();r.onsuccess=()=>resolve(r.result.slice(0,100));r.onerror=()=>reject(r.error);});
   if(!items.length)return;
   const response=await fetch('api/v1/scan.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({batch:items})});
   if(!response.ok)throw new Error('Scan sync failed; queued events retained.');
   const body=await response.json();if(!body.success || !Array.isArray(body.data?.synced))throw new Error('Invalid sync response.');
   const sent=new Set(items.map(item=>item.event_id));
   const acknowledged=body.data.synced.filter(r=>r.acknowledged===true && sent.has(r.event_id));
   await new Promise((resolve,reject)=>{const tx=db.transaction('events','readwrite');for(const r of acknowledged)tx.objectStore('events').delete(r.event_id);tx.oncomplete=resolve;tx.onerror=()=>reject(tx.error);});
   // Terminal denials were durably recorded: show them explicitly, not as successful entry.
   const denied=acknowledged.filter(r=>!r.success);
   if(denied.length)window.alert(denied.map(r=>r.message).join('\n'));
  } finally {syncing=false;}
 }
 async function submit(token,action='checkin') {
  await queue(token,action);
  if(!navigator.onLine){window.alert('Queued for reconciliation. Entry is NOT authorized offline.');return;}
  await sync();location.reload();
 }
 ready.then(()=>sync()).catch(console.error);
 window.addEventListener('online',()=>sync().catch(console.error));
 return {queue,sync,submit,getQueueCount,isOnline:()=>navigator.onLine};
})();
