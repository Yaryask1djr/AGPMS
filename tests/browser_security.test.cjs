const fs=require('fs'),vm=require('vm'),assert=require('assert');
const events={}, scope='https://example.test/AGPMS/';
const context={self:{registration:{scope},addEventListener:(name,fn)=>events[name]=fn},URL,Set};
vm.createContext(context);vm.runInContext(fs.readFileSync(__dirname+'/../sw.js','utf8'),context);
for(const path of ['security_dashboard.php','download_users.php','login_admin.php','api/v1/passes.php','assets/css/style.css?private=1']){
 let intercepted=false;events.fetch({request:{method:'GET',url:scope+path},respondWith:()=>intercepted=true});assert.equal(intercepted,false,path+' must be network-only');
}
console.log('PASS: service worker ignores authenticated and query-string routes.');

(async()=>{
 const records=new Map();let finishFetch,requestBody;
 const db={transaction(name,mode){
  const tx={};const complete=()=>setTimeout(()=>tx.oncomplete?.(),0);
  tx.objectStore=()=>({add(item){records.set(item.event_id,item);complete();},delete(id){records.delete(id);complete();},count(){const r={};setTimeout(()=>{r.result=records.size;r.onsuccess();},0);return r;},getAll(){const r={};const snapshot=[...records.values()];setTimeout(()=>{r.result=snapshot;r.onsuccess();},0);return r;}});return tx;
 }};
 let seq=0;const nav={onLine:false};
 const window={indexedDB:true,addEventListener(){},alert(){}};
 const ctx={window,navigator:nav,document:{querySelector:sel=>({content:sel.includes('scan-owner')?'1':'csrf-test'})},crypto:{randomUUID:()=>String(++seq)},Date,console,location:{reload(){}},fetch:async(url,opts)=>{
  requestBody=JSON.parse(opts.body);return new Promise(resolve=>finishFetch=()=>resolve({ok:true,json:async()=>({success:true,data:{synced:[{event_id:'1',acknowledged:true,success:false,message:'Denied'},{event_id:'2',acknowledged:false,success:false}]}})}));
 },indexedDB:{open(){const r={result:db};setTimeout(()=>r.onsuccess(),0);return r;}}};
 vm.createContext(ctx);vm.runInContext(fs.readFileSync(__dirname+'/../assets/js/offline-scanner.js','utf8'),ctx);
 const scanner=window.OfflineScanner;await scanner.queue('token-a');await scanner.queue('token-b');nav.onLine=true;
 const sync=scanner.sync();while(!finishFetch)await new Promise(resolve=>setTimeout(resolve,1));
 await scanner.queue('new-event');finishFetch();await sync;
 assert.equal(await scanner.getQueueCount(),2);assert(records.has('2'));assert(records.has('3'));assert(!records.has('1'));assert.equal(requestBody.batch.length,2);
 console.log('PASS: actual scanner retains retryable and concurrently queued events, and deletes only durable acknowledgements.');
})().catch(error=>{console.error(error);process.exitCode=1;});
