<div class="disclaimer" role="note"><strong>Disclaimer:</strong> This website is created for demonstration and academic purposes only as part of an SIA subject project. It is not a real company, official service, registered business, or commercial website. No claims are made regarding actual business operations, legal standing, or real-world services.</div>
  <nav class="auth-foot" aria-label="Legal">
    <button type="button" data-legal="legal-privacy">Privacy Policy</button>
    <button type="button" data-legal="legal-terms">Terms</button>
    <button type="button" data-legal="legal-moderation">Moderation</button>
    <button type="button" data-legal="legal-copyright">Copyright</button>
  </nav>
  <p class="auth-copy">&copy; <?= date('Y') ?> <?= e(STORE_NAME) ?></p>
  </div>
</div>

<dialog id="legal-privacy">
  <h3>Privacy Policy</h3>
  <div class="terms-body">
    <p><b>What we store.</b> Your name, username, a hashed (not readable) password, and the inventory, sales and report records you create.</p>
    <p><b>How it is used.</b> Your data is used only to run this demonstration system. It is not sold or shared with third parties.</p>
    <p><b>AI features.</b> Questions you send to the AI chat may be processed by an external AI service (Google Gemini).</p>
    <p><b>Academic project.</b> Do not enter real personal, financial or business information. This system was made for a school subject only.</p>
  </div>
  <div class="dlg-actions"><button type="button" class="btn" data-close>Close</button></div>
</dialog>
<dialog id="legal-terms">
  <h3>Terms</h3>
  <div class="terms-body">
    <p><b>Use of the system.</b> This is a demonstration inventory and sales system for an academic (SIA) project. Use it only to try out its features.</p>
    <p><b>Your account.</b> Keep your username and password private. You are responsible for what is done under your account.</p>
    <p><b>No guarantee.</b> The system is provided "as is". It may be unavailable, reset or changed at any time, so do not rely on it for real business records.</p>
    <p>The full Terms and Agreement is shown when you create an account.</p>
  </div>
  <div class="dlg-actions"><button type="button" class="btn" data-close>Close</button></div>
</dialog>
<dialog id="legal-moderation">
  <h3>Moderation</h3>
  <div class="terms-body">
    <p><b>Proper use.</b> Do not enter false or offensive records, try to access data you are not allowed to see, or interfere with how the system works.</p>
    <p><b>Suspension.</b> Accounts that break these rules may be suspended or removed, and their records may be deleted.</p>
  </div>
  <div class="dlg-actions"><button type="button" class="btn" data-close>Close</button></div>
</dialog>
<dialog id="legal-copyright">
  <h3>Copyright</h3>
  <div class="terms-body">
    <p>&copy; <?= date('Y') ?> <?= e(STORE_NAME) ?>. This website and its content were created by students for an academic (SIA) project.</p>
    <p>Product names that appear in the demo data belong to their respective owners and are used for demonstration only.</p>
  </div>
  <div class="dlg-actions"><button type="button" class="btn" data-close>Close</button></div>
</dialog>

<script>
document.querySelectorAll('[data-legal]').forEach(function(b){
  b.addEventListener('click', function(){ document.getElementById(b.dataset.legal).showModal(); });
});
</script>
<script src="assets/js/app.js"></script>
</body>
</html>