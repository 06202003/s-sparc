<?php 
	include("_sessionchecker.php"); 
	include("_config.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: About</title>
	<link rel="icon" href="strange_html_layout_additional_files/icon.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
	<script src="https://cdn.tailwindcss.com"></script>

	<style>
		:root { color-scheme: light; }
		body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
	</style>
  <style>
/* Premium Teal Dropdown Styling for E-STRANGE & S-SPARC */
/* Ensure SweetAlert2 hidden select is never displayed */
.swal2-container select,
.swal2-popup select,
.swal2-select {
  display: none !important;
}

select:not(.select2-hidden-accessible):not(.swal2-select), .form-select, .custom-select {
  appearance: none !important;
  -webkit-appearance: none !important;
  -moz-appearance: none !important;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2300A0A5' stroke-width='2.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E") !important;
  background-repeat: no-repeat !important;
  background-position: right 0.85rem center !important;
  background-size: 1.15rem 1.15rem !important;
  padding-left: 1rem !important;
  padding-right: 2.5rem !important;
  padding-top: 0.5rem !important;
  padding-bottom: 0.5rem !important;
  min-width: 130px !important;
  min-height: 40px !important;
  border-radius: 0.75rem !important;
  border: 1.5px solid #cbd5e1 !important;
  background-color: #ffffff !important;
  color: #0f172a !important;
  font-weight: 600 !important;
  font-size: 0.875rem !important;
  line-height: 1.25rem !important;
  transition: all 0.2s ease-in-out !important;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
  cursor: pointer !important;
  flex-shrink: 0 !important;
  display: inline-block !important;
  box-sizing: border-box !important;
}

select:not(.select2-hidden-accessible):not(.swal2-select):hover, .form-select:hover {
  border-color: #00A0A5 !important;
  background-color: #f8fafc !important;
  box-shadow: 0 4px 12px rgba(0, 160, 165, 0.08) !important;
}

select:not(.select2-hidden-accessible):not(.swal2-select):focus, .form-select:focus {
  outline: none !important;
  border-color: #00A0A5 !important;
  box-shadow: 0 0 0 3px rgba(0, 160, 165, 0.2) !important;
  background-color: #ffffff !important;
}

/* Ensure Select2 Native Input Remains Completely Hidden */
select.select2-hidden-accessible {
  display: none !important;
  width: 0 !important;
  height: 0 !important;
  padding: 0 !important;
  margin: 0 !important;
  border: 0 !important;
  opacity: 0 !important;
  position: absolute !important;
  pointer-events: none !important;
}

/* Select2 Plugin Custom Teal Enhancements */
.select2-container--default .select2-selection--single {
  border-radius: 0.75rem !important;
  border: 1.5px solid #cbd5e1 !important;
  height: 42px !important;
  min-width: 140px !important;
  padding: 6px 12px !important;
  font-weight: 600 !important;
  font-size: 0.875rem !important;
  transition: all 0.2s ease-in-out !important;
}

.select2-container--default .select2-selection--single:hover {
  border-color: #00A0A5 !important;
}

.select2-container--default.select2-container--open .select2-selection--single,
.select2-container--default.select2-container--focus .select2-selection--single {
  border-color: #00A0A5 !important;
  box-shadow: 0 0 0 3px rgba(0, 160, 165, 0.2) !important;
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
  background-color: #00A0A5 !important;
  color: #ffffff !important;
}

</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 flex flex-col">
	<?php
		if ($_SESSION['role'] == 'admin') {
			setHeaderAdmin("about", "About");
		} else if ($_SESSION['role'] == 'lecturer') {
			setHeaderLecturer("about", "About");
		} else if ($_SESSION['role'] == 'student') {
			setHeaderStudent("about", "About");
		}
	?>

	<main class="flex-1 py-10 flex items-center justify-center">
		<div class="max-w-3xl w-full mx-auto px-4 sm:px-6">
			
			<div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
				
				<!-- Header -->
				<div class="px-8 pt-8 pb-6 border-b border-slate-100 bg-slate-50/50">
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
							Platform Overview
						</span>
						<span class="text-xs font-semibold text-slate-500">
							Academic Intelligence
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight">About E-STRANGE</h1>
					<p class="text-xs text-slate-500 mt-1">Source Code Plagiarism &amp; Structural Similarity Analysis Platform for Academia.</p>
				</div>

				<!-- Content -->
				<div class="p-8 space-y-6 text-xs text-slate-700 leading-relaxed">
					<div class="p-5 bg-slate-50 border border-slate-200 rounded-2xl space-y-2">
						<h2 class="text-sm font-bold text-slate-900">STRANGE Educational Mode (E-STRANGE)</h2>
						<p class="text-slate-600">
							<strong>E-STRANGE</strong> is an automated source code submission and intelligence framework tailored for academic computing courses. The platform guides students in developing sound programming ethics, code clarity, and structural efficiency through similarity analytics, peer reviews, and gamified learning.
						</p>
					</div>

					<div class="space-y-3">
						<h3 class="text-xs font-bold uppercase tracking-wider text-slate-900">Contributors &amp; Academic Leadership</h3>
						<ol class="space-y-2 text-slate-600 list-decimal list-inside pl-1 font-medium">
							<li><span class="font-bold text-slate-800">Oscar Karnalim</span> &mdash; Primary Creator &amp; Project Lead</li>
							<li><span class="font-bold text-slate-800">Simon</span> &mdash; Architectural &amp; Algorithmic Advisory (v1&ndash;v3)</li>
							<li><span class="font-bold text-slate-800">William Chivers</span> &mdash; Academic &amp; Research Advisory (v1&ndash;v3)</li>
							<li><span class="font-bold text-slate-800">Billy Susanto Panca</span> &mdash; Initial Server Architecture &amp; Database Design</li>
							<li><span class="font-bold text-slate-800">Gisela Kurniawati</span> &mdash; Visual Identity &amp; Platform Iconography</li>
							<li><span class="font-bold text-slate-800">Yehezkiel David Setiawan</span> &mdash; UI Architecture &amp; User Experience Modernization</li>
							<li><span class="font-bold text-slate-800">Rossevine Artha Nathasya &amp; Sendy Ferdian Sujadi</span> &mdash; Infrastructure &amp; Deployment Operations</li>
						</ol>
					</div>

					<div class="pt-4 border-t border-slate-100 flex items-center justify-between">
						<span class="text-slate-400 text-[11px]">Maratha Christian University / Academic Computing</span>
						<a href="mailto:oscar.karnalim@it.maranatha.edu" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#00A0A5] hover:bg-[#008488] text-white text-xs font-bold rounded-xl transition shadow-2xs">
							<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
							<span>Contact Project Lead</span>
						</a>
					</div>
				</div>

			</div>

		</div>
	</main>
</body>
</html>
