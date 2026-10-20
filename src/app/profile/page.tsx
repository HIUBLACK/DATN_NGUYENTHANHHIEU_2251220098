"use client";
import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";
import { ArrowUpTrayIcon, DocumentTextIcon, ArrowUpRightIcon, CheckIcon, SparklesIcon } from "@heroicons/react/24/outline";

type Profile = { name: string; email: string; phone: string; title: string; location: string; skills: string; about: string };
const empty: Profile = { name: "", email: "", phone: "", title: "", location: "", skills: "", about: "" };
const fields = [
  { label: "Họ và tên", key: "name", placeholder: "Nguyễn Minh Anh", autoComplete: "name" },
  { label: "Email", key: "email", placeholder: "ban@email.com", autoComplete: "email" },
  { label: "Số điện thoại", key: "phone", placeholder: "Nhập số điện thoại", autoComplete: "tel" },
  { label: "Chức danh mong muốn", key: "title", placeholder: "Ví dụ: Frontend Developer", autoComplete: "organization-title" },
  { label: "Địa điểm làm việc", key: "location", placeholder: "Ví dụ: TP. Hồ Chí Minh", autoComplete: "address-level2" },
  { label: "Kỹ năng của bạn", key: "skills", placeholder: "Ví dụ: React, Python, SQL", autoComplete: "off" },
] as const;

export default function ProfilePage() {
  const [profile, setProfile] = useState<Profile>(empty);
  const [cv, setCv] = useState("");
  const [notice, setNotice] = useState("");
  useEffect(() => {
    const sync = () => { try { const user = localStorage.getItem("viejob-profile"); if (user) setProfile({ ...empty, ...JSON.parse(user) }); setCv(localStorage.getItem("viejob-cv") || ""); } catch { setNotice("Chưa thể đọc hồ sơ đã lưu."); } };
    sync(); window.addEventListener("viejob-auth-changed", sync); return () => window.removeEventListener("viejob-auth-changed", sync);
  }, []);
  useEffect(() => { if (!notice) return; const timer = window.setTimeout(() => setNotice(""), 4500); return () => window.clearTimeout(timer); }, [notice]);
  function save(event: FormEvent) {
    event.preventDefault();
    try { localStorage.setItem("viejob-profile", JSON.stringify(profile)); setNotice("Đã lưu hồ sơ. Bạn đã sẵn sàng cho cơ hội tiếp theo!"); }
    catch { setNotice("Không thể lưu hồ sơ. Vui lòng kiểm tra dung lượng trình duyệt."); }
  }
  function upload(file?: File) {
    if (!file) return;
    if (!/\.(pdf|doc|docx)$/i.test(file.name)) { setNotice("Vui lòng chọn tệp PDF, DOC hoặc DOCX."); return; }
    if (file.size > 5 * 1024 * 1024) { setNotice("CV cần có dung lượng dưới 5MB."); return; }
    try { localStorage.setItem("viejob-cv", file.name); setCv(file.name); setNotice("Đã ghi nhận tên CV. Bản trải nghiệm chưa tải nội dung tệp lên máy chủ."); }
    catch { setNotice("Không thể lưu tên CV trên trình duyệt này."); }
  }
  const completion = Math.round((Object.values(profile).filter(value => value.trim()).length + (cv ? 1 : 0)) / 8 * 100);
  return <div className="inner-page"><section className="page-banner"><div className="site-container"><span className="eyebrow">DẤU ẤN RIÊNG CỦA BẠN</span><h1>Một hồ sơ.<span> Ngàn tiềm năng.</span></h1><p>Để kỹ năng, kinh nghiệm và câu chuyện của bạn lên tiếng.</p><span className="banner-decoration" aria-hidden="true">✳</span></div></section>
    <div className="site-container page-content account-layout"><form onSubmit={save} className="panel"><div className="profile-heading"><span className="profile-avatar">{profile.name ? profile.name.charAt(0).toUpperCase() : "V"}</span><div><h2 className="panel-title">{profile.name || "Hồ sơ của bạn"}</h2><p className="panel-description">Một chút về bạn, rất nhiều cơ hội phía trước.</p></div></div><div className="form-grid">{fields.map(({ label, key, placeholder, autoComplete }) => <label key={key}>{label}{(key === "name" || key === "email") && " *"}<input required={key === "name" || key === "email"} type={key === "email" ? "email" : key === "phone" ? "tel" : "text"} autoComplete={autoComplete} value={profile[key]} placeholder={placeholder} onChange={event => setProfile({ ...profile, [key]: event.target.value })} /></label>)}<label className="form-wide">Giới thiệu bản thân<textarea value={profile.about} onChange={event => setProfile({ ...profile, about: event.target.value })} rows={5} placeholder="Chia sẻ một chút về kinh nghiệm, thành tựu và điều bạn mong muốn ở công việc tiếp theo…" /></label></div><div className="form-actions"><span>* Thông tin bắt buộc · Hồ sơ lưu trên trình duyệt</span><button className="button button-primary" type="submit">Lưu hồ sơ <CheckIcon /></button></div></form>
      <aside className="account-sidebar"><div className="panel"><div className="completion-title"><h2>Mức độ hoàn thiện</h2><strong>{completion}%</strong></div><div className="completion-meter" role="progressbar" aria-label="Mức độ hoàn thiện hồ sơ" aria-valuemin={0} aria-valuemax={100} aria-valuenow={completion}><span style={{ width: `${completion}%` }} /></div><p className="panel-description">{completion === 100 ? "Hồ sơ của bạn đã sẵn sàng. Cùng khám phá cơ hội mới!" : "Mỗi thông tin bạn thêm là một cách để cơ hội hiểu bạn hơn."}</p></div>
        <div className="panel"><h2 className="panel-title">CV của tôi</h2><p className="panel-description">Chuẩn bị CV cho bước tiến tiếp theo.</p>{cv && <div className="cv-file"><DocumentTextIcon /><span title={cv}>{cv}</span><button type="button" onClick={() => { localStorage.removeItem("viejob-cv"); setCv(""); }} aria-label="Xóa CV">Xóa</button></div>}<label className="upload-area"><ArrowUpTrayIcon /><strong>{cv ? "Chọn CV khác" : "Nhấn để chọn CV của bạn"}</strong><span>PDF, DOC, DOCX · Tối đa 5 MB</span><input aria-label="Chọn tệp CV" type="file" accept=".pdf,.doc,.docx" onChange={event => { upload(event.target.files?.[0]); event.target.value = ""; }} /></label><p className="data-note">Bản trải nghiệm chỉ lưu tên tệp CV.</p></div>
        <div className="sidebar-promo"><SparklesIcon /><h3>Tiếp theo, tìm nơi bạn thuộc về.</h3><p>Khám phá các vị trí phù hợp với lĩnh vực bạn quan tâm.</p><Link href="/jobs" className="text-link">Khám phá việc làm <ArrowUpRightIcon /></Link></div>
      </aside>
    </div>{notice && <div className="toast" role="status">{notice}</div>}
  </div>;
}
