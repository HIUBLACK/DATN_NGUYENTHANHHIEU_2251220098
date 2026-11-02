const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
vm.runInThisContext(fs.readFileSync('public/assets/js/platform-domain.js','utf8'));
const rules = globalThis.ViejobRules;
test('application allows only initial attempt and one reapplication after withdrawal',()=>{
    const job={id:1,status:'open'};
    assert.equal(rules.applicationEligibility(job,[]).attempt,1);
    assert.equal(rules.applicationEligibility(job,[{jobId:1,status:'applied'}]).allowed,false);
    assert.equal(rules.applicationEligibility(job,[{jobId:1,status:'withdrawn'}]).attempt,2);
    assert.equal(rules.applicationEligibility(job,[{jobId:1,status:'withdrawn'},{jobId:1,status:'withdrawn'}]).allowed,false);
    for(const status of ['closed','expired','hidden']) assert.equal(rules.applicationEligibility({...job,status},[]).allowed,false);
    assert.equal(rules.applicationEligibility(null,[]).allowed,false);
});
test('withdraw only active applications',()=>{
    for(const status of ['applied','reviewing','interview']) assert.equal(rules.canWithdraw(status),true);
    for(const status of ['hired','rejected','withdrawn']) assert.equal(rules.canWithdraw(status),false);
});
test('PDF limits accept up to 5 MB, reject other files and an eleventh CV',()=>{
    const file={name:'cv.pdf',type:'application/pdf',size:5*1024*1024};
    assert.equal(rules.pdfError(file,9),'');
    assert.notEqual(rules.pdfError({...file,size:file.size+1},0),'');
    assert.notEqual(rules.pdfError({...file,name:'cv.exe'},0),'');
    assert.notEqual(rules.pdfError({...file,type:'image/png'},0),'');
    assert.notEqual(rules.pdfError({...file,size:0},0),'');
    assert.notEqual(rules.pdfError(file,10),'');
    assert.equal(rules.pdfError(file,10,true),'');
});
test('skills normalize case and spacing without duplicates',()=>{
    assert.deepEqual(rules.normalizeSkills([' php ','PHP','rest   api','Laravel','']),['PHP','REST API','Laravel']);
});
