"use client";
import Link from "next/link";
import { BookmarkIcon, MapPinIcon, ArrowUpRightIcon, SparklesIcon } from "@heroicons/react/24/outline";
import type { Job } from "@/lib/jobs";

export function JobCard({ job, saved, onSave }: { job: Job; saved: boolean; onSave: () => void }) {
  return <article className="job-card">
    <div className="job-card-top"><span className={`company-logo logo-${job.id % 6}`}>{job.logo}</span><div><span className="company-name">{job.company}</span><span className="job-posted">{job.posted}</span></div><button className={`save-button ${saved ? "is-saved" : ""}`} onClick={onSave} aria-label={`${saved ? "Bỏ lưu" : "Lưu"} ${job.title}`} aria-pressed={saved}><BookmarkIcon /></button></div>
    <Link href={`/jobs/${job.id}`} className="job-title">{job.title}<ArrowUpRightIcon /></Link>
    <div className="job-meta"><span><MapPinIcon />{job.location}</span><span className="meta-dot" />{job.type}</div>
    <div className="skill-tags">{job.skills.map(skill => <span key={skill}>{skill}</span>)}</div>
    <div className="job-card-footer"><span className="salary">{job.salary} triệu <small>/ tháng</small></span><span className="match-badge" title="Điểm phù hợp minh họa"><SparklesIcon />{job.match}% phù hợp</span></div>
  </article>;
}
