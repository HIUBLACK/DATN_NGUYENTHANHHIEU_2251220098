"use client";
import { useEffect, useState } from "react";
import Link from "next/link";
import { jobs, readIds, savedKey, appliedKey } from "@/lib/jobs";
import { ArrowLeftIcon, BookmarkIcon, MapPinIcon, BriefcaseIcon, SparklesIcon, CheckCircleIcon, ClockIcon, ArrowUpRightIcon } from "@heroicons/react/24/outline";

export default function JobDetailPage({ params }: { params: { id: string } }) {
  const job = jobs.find(item => item.id === Number(params.id));
  const [saved, setSaved] = useState(false);
  const [applied, setApplied] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  useEffect(() => {
    if (job) { setSaved(readIds(savedKey).includes(job.id)); setApplied(readIds(appliedKey).includes(job.id)); }
    setMessage(""); setError("");
  }, [job]);
  if (!job) return <div className="inner-page"><div className="site-container page-content"><div className="empty-state"><BriefcaseIcon /><h1 className="panel-title">Không tìm thấy công việc này.</h1><p>Khám phá thêm các cơ hội khác đang chờ bạn.</p><Link className="button button-primary" href="/jobs">Quay lại tìm việc <ArrowUpRightIcon /></Link></div></div></div>;
  function toggleSave() {
    try { const id = Number(params.id); const ids = readIds(savedKey); const next = ids.includes(id) ? ids.filter(item => item !== id) : [...ids, id]; localStorage.setItem(savedKey, JSON.stringify(next)); setSaved(next.includes(id)); }
    catch { setError("Không thể lưu việc làm trên trình duyệt này."); }
  }
  function apply() {
    if (applied) return;
    try { const id = Number(params.id); const ids = readIds(appliedKey); localStorage.setItem(appliedKey, JSON.stringify(Array.from(new Set([...ids, id])))); setApplied(true); setMessage("Đã lưu đơn ứng tuyển của bạn trong bản trải nghiệm."); }
    catch { setError("Không thể lưu đơn ứng tuyển trên trình duyệt này."); }
  }
  return <div className="inner-page"><div className="site-container detail-wrap"><Link href="/jobs" className="back-link"><ArrowLeftIcon />Quay lại khám phá việc làm</Link>{message && <div role="status" className="success-banner"><CheckCircleIcon />{message}<Link href="/applications">Xem đơn ứng tuyển →</Link></div>}{error && <p role="alert" className="form-error">{error}</p>}
    <div className="detail-layout"><article className="panel"><div className="detail-header"><span className={`company-logo logo-${job.id % 6}`}>{job.logo}</span><div><span className="eyebrow">{job.category}</span><h1>{job.title}</h1><p>{job.company}</p></div></div><div className="detail-facts"><div><MapPinIcon /><div><span>Địa điểm làm việc</span><strong>{job.location}</strong></div></div><div><BriefcaseIcon /><div><span>Hình thức làm việc</span><strong>{job.type}</strong></div></div><div><ClockIcon /><div><span>Thời gian đăng</span><strong>{job.posted}</strong></div></div></div><div className="detail-prose"><h2>Cơ hội dành cho bạn</h2><p>{job.description}</p><h2>Kỹ năng bạn sẽ sử dụng</h2><div className="skill-tags">{job.skills.map(skill => <span key={skill}>{skill}</span>)}</div><h2>Thông tin tuyển dụng</h2><p>Ngành nghề: {job.category}<br />Hình thức: {job.type}<br />Mức lương tham khảo: {job.salary} triệu đồng / tháng</p><h2>Sẵn sàng cho bước tiếp theo?</h2><p>Hãy cập nhật hồ sơ và chuẩn bị CV thể hiện kinh nghiệm, kỹ năng của bạn trước khi ứng tuyển.</p><Link className="text-link" href="/profile" style={{ marginTop: 18 }}>Hoàn thiện hồ sơ của tôi <ArrowUpRightIcon /></Link></div></article>
      <aside className="panel detail-aside"><span className="eyebrow">BƯỚC TIẾN TIẾP THEO</span><h2>Một cơ hội mới.<br />Một tương lai khác.</h2><p className="panel-description">{job.company}</p><span className="salary">{job.salary} triệu <small>/ tháng</small></span><button onClick={apply} disabled={applied} className={`button ${applied ? "button-applied" : "button-primary"}`}>{applied ? <><CheckCircleIcon />Đã ứng tuyển</> : <>Ứng tuyển ngay <ArrowUpRightIcon /></>}</button><button onClick={toggleSave} className={`button ${saved ? "button-soft" : "button-outline"}`} aria-pressed={saved}><BookmarkIcon fill={saved ? "currentColor" : "none"} />{saved ? "Đã lưu việc làm" : "Lưu để xem sau"}</button><Link href="/applications" className="text-link">Theo dõi đơn ứng tuyển <ArrowUpRightIcon /></Link><div className="detail-match"><span><SparklesIcon />{job.match}% phù hợp</span><p>Điểm minh họa trong bản trải nghiệm, chưa được tính từ hồ sơ cá nhân.</p></div><p className="data-note">Thao tác ứng tuyển chỉ lưu trên trình duyệt, chưa gửi đến nhà tuyển dụng.</p></aside>
    </div>
  </div></div>;
}
