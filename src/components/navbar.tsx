"use client";
import { FormEvent, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { XMarkIcon, Bars3Icon, ArrowUpRightIcon, SparklesIcon } from "@heroicons/react/24/outline";

type Account = { name: string; email: string; passwordHash: string };
async function hashPassword(password: string) {
  const hash = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(password));
  return Array.from(new Uint8Array(hash)).map(byte => byte.toString(16).padStart(2, "0")).join("");
}
const links = [{ href: "/jobs", label: "Tìm việc làm" }, { href: "/applications", label: "Đơn ứng tuyển" }, { href: "/profile", label: "Hồ sơ của tôi" }];

export function Navbar() {
  const pathname = usePathname();
  const [open, setOpen] = useState(false);
  const [user, setUser] = useState("");
  const [auth, setAuth] = useState<"Đăng nhập" | "Đăng ký" | null>(null);
  const [error, setError] = useState("");
  const [pending, setPending] = useState(false);
  const dialog = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const sync = () => setUser(localStorage.getItem("viejob-user") || "");
    sync(); window.addEventListener("viejob-auth-changed", sync); window.addEventListener("storage", sync);
    return () => { window.removeEventListener("viejob-auth-changed", sync); window.removeEventListener("storage", sync); };
  }, []);
  useEffect(() => { setOpen(false); }, [pathname]);
  useEffect(() => {
    if (auth) { dialog.current?.showModal(); const previous = document.body.style.overflow; document.body.style.overflow = "hidden"; return () => { document.body.style.overflow = previous; }; }
    dialog.current?.close();
  }, [auth]);
  function openAuth(mode: "Đăng nhập" | "Đăng ký") { setError(""); setAuth(mode); setOpen(false); }
  async function submitAuth(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setError(""); setPending(true);
    const data = new FormData(event.currentTarget);
    const email = String(data.get("email")).trim().toLowerCase();
    const password = String(data.get("password"));
    const name = String(data.get("name") || email.split("@")[0]).trim();
    try {
      const accounts = JSON.parse(localStorage.getItem("viejob-accounts") || "[]") as Account[];
      const existing = accounts.find(account => account.email === email);
      const passwordHash = await hashPassword(password);
      if (auth === "Đăng ký") {
        if (existing) { setError("Email này đã được đăng ký. Hãy đăng nhập."); return; }
        accounts.push({ name, email, passwordHash });
        localStorage.setItem("viejob-accounts", JSON.stringify(accounts));
        localStorage.setItem("viejob-profile", JSON.stringify({ name, email, phone: "", title: "", location: "", skills: "", about: "" }));
      } else if (!existing || existing.passwordHash !== passwordHash) { setError("Email hoặc mật khẩu chưa chính xác."); return; }
      const displayName = auth === "Đăng ký" ? name : existing!.name;
      localStorage.setItem("viejob-user", displayName); setUser(displayName); setAuth(null);
      window.dispatchEvent(new Event("viejob-auth-changed"));
    } catch { setError("Không thể lưu tài khoản trên trình duyệt này. Vui lòng thử lại."); }
    finally { setPending(false); }
  }
  const navLinks = links.map(link => <Link key={link.href} href={link.href} className={`nav-link ${pathname.startsWith(link.href) ? "active" : ""}`} aria-current={pathname.startsWith(link.href) ? "page" : undefined}>{link.label}</Link>);
  const authButtons = user ? <><Link href="/profile" className="user-chip"><span>{user.charAt(0).toUpperCase()}</span>{user}</Link><button className="button button-soft" onClick={() => { localStorage.removeItem("viejob-user"); setUser(""); window.dispatchEvent(new Event("viejob-auth-changed")); }}>Đăng xuất</button></> : <><button className="login-button" onClick={() => openAuth("Đăng nhập")}>Đăng nhập</button><button className="button button-primary" onClick={() => openAuth("Đăng ký")}>Bắt đầu ngay <ArrowUpRightIcon /></button></>;
  return <>
    <header className="site-header"><div className="site-container navbar-inner"><Link href="/" aria-label="VIEJOB — Trang chủ" className="brand"><span className="brand-symbol">v<span>↗</span></span>viejob<span className="brand-period">.</span></Link><nav className="desktop-nav" aria-label="Điều hướng chính">{navLinks}</nav><div className="nav-actions">{authButtons}</div><button className="mobile-menu-button" aria-label={open ? "Đóng menu" : "Mở menu"} aria-expanded={open} aria-controls="mobile-navigation" onClick={() => setOpen(!open)}>{open ? <XMarkIcon /> : <Bars3Icon />}</button></div>{open && <nav id="mobile-navigation" className="mobile-nav" aria-label="Điều hướng di động">{navLinks}<div>{authButtons}</div></nav>}</header>
    <dialog ref={dialog} className="auth-dialog" aria-labelledby="auth-title" onCancel={() => setAuth(null)} onClose={() => setAuth(null)} onClick={event => { if (event.target === event.currentTarget) setAuth(null); }}>
      <div className="auth-content"><button className="dialog-close" aria-label="Đóng" onClick={() => setAuth(null)}><XMarkIcon /></button><span className="auth-symbol"><SparklesIcon /></span><span className="eyebrow">CHƯƠNG TIẾP THEO CỦA BẠN</span><h2 id="auth-title">{auth === "Đăng nhập" ? "Chào mừng trở lại." : "Bắt đầu điều mới."}</h2><p>{auth === "Đăng nhập" ? "Cơ hội tiếp theo vẫn đang chờ bạn." : "Tạo tài khoản và viết tiếp câu chuyện sự nghiệp."}</p>
        <form key={auth} onSubmit={submitAuth} className="auth-form">{auth === "Đăng ký" && <label>Họ và tên<input name="name" autoComplete="name" required minLength={2} placeholder="Nguyễn Minh Anh" /></label>}<label>Email<input name="email" type="email" autoComplete="email" required placeholder="ban@email.com" /></label><label>Mật khẩu<input name="password" type="password" autoComplete={auth === "Đăng ký" ? "new-password" : "current-password"} minLength={6} required placeholder="Ít nhất 6 ký tự" /></label>{error && <p role="alert" className="form-error">{error}</p>}<button type="submit" disabled={pending} className="button button-primary">{pending ? "Đang xử lý…" : auth}<ArrowUpRightIcon /></button></form>
        <p className="auth-switch">{auth === "Đăng nhập" ? "Chưa có tài khoản?" : "Đã có tài khoản?"} <button onClick={() => openAuth(auth === "Đăng nhập" ? "Đăng ký" : "Đăng nhập")}>{auth === "Đăng nhập" ? "Đăng ký ngay" : "Đăng nhập"}</button></p><p className="data-note">Tài khoản trải nghiệm được lưu trên trình duyệt này.</p>
      </div>
    </dialog>
  </>;
}
export default Navbar;
