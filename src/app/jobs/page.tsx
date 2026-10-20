"use client";
import { Suspense, useEffect, useMemo, useState } from "react";
import { useSearchParams } from "next/navigation";
import Link from "next/link";
import { jobs, readIds, savedKey } from "@/lib/jobs";
import { JobCard } from "@/components/job-card";
import { MagnifyingGlassIcon, MapPinIcon, AdjustmentsHorizontalIcon, BookmarkIcon, SparklesIcon, ArrowUpRightIcon, XMarkIcon } from "@heroicons/react/24/outline";

function JobsContent() {
  const params = useSearchParams();
  const [query, setQuery] = useState("");
  const [location, setLocation] = useState("");
  const [category, setCategory] = useState("");
  const [type, setType] = useState("");
  const [sort, setSort] = useState("Phù hợp nhất");
  const [saved, setSaved] = useState<number[]>([]);
  const [onlySaved, setOnlySaved] = useState(false);
  useEffect(() => { setSaved(readIds(savedKey)); }, []);
  useEffect(() => { setQuery(params.get("q") || ""); setLocation(params.get("location") || ""); setCategory(params.get("category") || ""); setOnlySaved(params.get("saved") === "1"); }, [params]);
  const filtered = useMemo(() => {
    const list = jobs.filter(job => `${job.title} ${job.company} ${job.skills.join(" ")}`.toLocaleLowerCase("vi").includes(query.trim().toLocaleLowerCase("vi")) && (!location || job.location === location) && (!category || job.category === category) && (!type || job.type === type) && (!onlySaved || saved.includes(job.id)));
    return list.sort((a, b) => sort === "Mới đăng" ? a.age - b.age : sort === "Lương cao nhất" ? b.salary - a.salary : b.match - a.match);
  }, [query, location, category, type, sort, onlySaved, saved]);
  function toggleSave(id: number) { const current = readIds(savedKey); const next = current.includes(id) ? current.filter(item => item !== id) : [...current, id]; setSaved(next); localStorage.setItem(savedKey, JSON.stringify(next)); }
  function reset() { setQuery(""); setLocation(""); setCategory(""); setType(""); setOnlySaved(false); }
  const hasFilters = !!(query || location || category || type || onlySaved);
  return <div className="inner-page">
    <section className="page-banner"><div className="site-container"><span className="eyebrow"><span className="status-dot" />KHÁM PHÁ CƠ HỘI MỚI</span><h1>Tìm công việc.<br className="mobile-only" /> <span>Chạm đam mê.</span></h1><p>Cơ hội phù hợp đang chờ bạn, hãy bắt đầu khám phá.</p><span className="banner-decoration" aria-hidden="true">✳</span><div className="jobs-search"><label><MagnifyingGlassIcon /><input value={query} onChange={e => setQuery(e.target.value)} aria-label="Tìm theo chức danh, kỹ năng hoặc công ty" placeholder="Chức danh, kỹ năng hoặc công ty…" />{query && <button onClick={() => setQuery("")} aria-label="Xóa từ khóa"><XMarkIcon /></button>}</label><label><MapPinIcon /><select value={location} onChange={e => setLocation(e.target.value)} aria-label="Địa điểm"><option value="">Tất cả địa điểm</option>{Array.from(new Set(jobs.map(job => job.location))).map(item => <option key={item}>{item}</option>)}</select></label><span className="search-count">{filtered.length} cơ hội dành cho bạn <SparklesIcon /></span></div></div></section>
    <div className="site-container jobs-layout"><aside className="filter-sidebar"><div className="filter-heading"><h2><AdjustmentsHorizontalIcon />Bộ lọc tìm kiếm</h2>{hasFilters && <button onClick={reset}>Đặt lại</button>}</div><fieldset><legend>Ngành nghề</legend><label className="radio-option"><input type="radio" name="category" checked={!category} onChange={() => setCategory("")} />Tất cả ngành nghề<span>{jobs.length}</span></label>{Array.from(new Set(jobs.map(job => job.category))).map(item => <label className="radio-option" key={item}><input type="radio" name="category" checked={category === item} onChange={() => setCategory(item)} />{item}<span>{jobs.filter(job => job.category === item).length}</span></label>)}</fieldset><fieldset><legend>Hình thức làm việc</legend>{["", "Toàn thời gian", "Thực tập"].map(item => <label className="radio-option" key={item}><input type="radio" name="type" checked={type === item} onChange={() => setType(item)} />{item || "Tất cả hình thức"}</label>)}</fieldset><div className="sidebar-promo"><SparklesIcon /><h3>Để cơ hội hiểu bạn hơn.</h3><p>Thêm kỹ năng và kinh nghiệm vào hồ sơ của bạn.</p><Link className="text-link" href="/profile">Hoàn thiện hồ sơ <ArrowUpRightIcon /></Link></div></aside>
      <section className="job-results" aria-label="Kết quả tìm việc"><div className="results-toolbar"><p aria-live="polite"><strong>{filtered.length}</strong> việc làm phù hợp</p><button onClick={() => setOnlySaved(!onlySaved)} className={`saved-filter ${onlySaved ? "active" : ""}`} aria-pressed={onlySaved}><BookmarkIcon />Đã lưu ({saved.length})</button><label className="sort-control"><span>Sắp xếp:</span><select value={sort} onChange={e => setSort(e.target.value)} aria-label="Sắp xếp việc làm"><option>Phù hợp nhất</option><option>Mới đăng</option><option>Lương cao nhất</option></select></label></div>
        {hasFilters && <div className="active-filters">{[query, location, category, type, onlySaved ? "Việc đã lưu" : ""].filter(Boolean).map(item => <span key={item}>{item}</span>)}<button onClick={reset}>Xóa bộ lọc <XMarkIcon /></button></div>}
        <div className="job-grid jobs-list-grid">{filtered.map(job => <JobCard key={job.id} job={job} saved={saved.includes(job.id)} onSave={() => toggleSave(job.id)} />)}</div>
        {!filtered.length && <div className="empty-state"><MagnifyingGlassIcon /><h2>Thử một hướng tìm kiếm khác nhé.</h2><p>Chưa có công việc phù hợp với bộ lọc hiện tại.</p><button onClick={reset} className="button button-primary">Xóa bộ lọc, khám phá lại</button></div>}
        <p className="data-note">Việc làm và điểm phù hợp là dữ liệu minh họa.</p>
      </section>
    </div>
  </div>;
}

export default function JobsPage() {
  return <Suspense fallback={<div className="inner-page"><div className="site-container page-content"><p role="status">Đang chuẩn bị các cơ hội dành cho bạn…</p></div></div>}><JobsContent /></Suspense>;
}
