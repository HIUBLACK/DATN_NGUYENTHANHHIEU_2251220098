"use client";
import { useEffect } from "react";
import { usePathname } from "next/navigation";

export function MotionEffects() {
  const pathname = usePathname();
  useEffect(() => {
    const elements = document.querySelectorAll<HTMLElement>("[data-reveal]");
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches || !("IntersectionObserver" in window)) return;
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
      if (entry.isIntersecting) { entry.target.classList.add("is-visible"); observer.unobserve(entry.target); }
    }), { threshold: 0.08 });
    elements.forEach(element => { element.classList.add("reveal-ready"); observer.observe(element); });
    return () => { observer.disconnect(); elements.forEach(element => element.classList.remove("reveal-ready")); };
  }, [pathname]);
  useEffect(() => {
    let frame = 0;
    const update = () => { cancelAnimationFrame(frame); frame = requestAnimationFrame(() => {
      const height = document.documentElement.scrollHeight - window.innerHeight;
      document.documentElement.style.setProperty("--scroll-progress", `${height > 0 ? window.scrollY / height : 0}`);
    }); };
    update(); window.addEventListener("scroll", update, { passive: true }); window.addEventListener("resize", update);
    return () => { cancelAnimationFrame(frame); window.removeEventListener("scroll", update); window.removeEventListener("resize", update); };
  }, [pathname]);
  return <div className="scroll-progress" aria-hidden="true" />;
}
