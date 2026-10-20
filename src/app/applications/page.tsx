"use client";
import { useEffect, useState } from "react";
import Link from "next/link";
import { jobs, readIds, appliedKey, savedKey } from "@/lib/jobs";
import { DocumentTextIcon, ClockIcon, BookmarkIcon, ArrowUpRightIcon } from "@heroicons/react/24/outline";

export default function ApplicationsPage() {
  const [ids, setIds] = useState<number[]>([]);
  const [saved, setSaved] = useState(0);
  useEffect(() => { setIds(readIds(appliedKey)); setSaved(readIds(savedKey).length); }, []);
  const applications = jobs.filter(job => ids.includes(job.id));
  return <div className="inner-page"><section className="page-banner"><div className="site-container"><span className="eyebrow">MỖI CƠ HỘI, MỘT BƯỚC TIẾN</span><h1>Hành trình <span>của bạn.</span></h1><p>Theo dõi đơn ứng tuyển và sẵn sàng cho những tin vui tiếp theo.</p><span className="banner-decoration" aria-hidden="true">✳</span></div></section><div className="site-container page-content">
    <div className="summary-grid"><div className="summary-card"><DocumentTextIcon /><div><strong>{applications.length.toString().padStart(2, "0")}</strong><span>Đơn đã ứng tuyển</span></div></div><div className="summary-card"><ClockIcon /><div><strong>{applications.length.toString().padStart(2, "0")}</strong><span>Đang chờ phản hồi</span></div></div><Link href="/jobs?saved=1" className="summary-card"><BookmarkIcon /><div><strong>{saved.toString().padStart(2, "0")}</strong><span>Việc làm đã lưu · Xem việc →</span></div></Link></div>
    <div className="section-heading"><div><h2 style={{ fontSize: 22 }}>Đơn ứng tuyển của bạn</h2><p>Mọi cơ hội bạn đã chọn, cùng một nơi.</p></div><Link className="text-link" href="/jobs">Tìm thêm cơ hội <ArrowUpRightIcon /></Link></div>
    <section className="applications-table" aria-label="Danh sách đơn ứng tuyển"><div className="table-heading" aria-hidden="true"><span>VỊ TRÍ ỨNG TUYỂN</span><span>ĐỊA ĐIỂM</span><span>TRẠNG THÁI</span><span /></div>{applications.length ? applications.map(job => <Link href={`/jobs/${job.id}`} className="application-row" key={job.id}><div className="application-position"><span className={`company-logo logo-${job.id % 6}`}>{job.logo}</span><div><strong>{job.title}</strong><span>{job.company}</span></div></div><span>{job.location}</span><span className="pending-badge">● Đang chờ phản hồi</span><ArrowUpRightIcon /></Link>) : <div className="empty-state"><DocumentTextIcon /><h2>Chương mới đang chờ được viết.</h2><p>Bạn chưa có đơn ứng tuyển nào.<br />Khám phá công việc yêu thích và bắt đầu hành trình của mình.</p><Link href="/jobs" className="button button-primary">Khám phá việc làm <ArrowUpRightIcon /></Link></div>}</section><p className="data-note">Đơn ứng tuyển trong bản trải nghiệm được lưu trên trình duyệt, chưa gửi đến nhà tuyển dụng.</p>
  </div></div>;
}
