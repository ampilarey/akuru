# 🎨 Akuru Institute Website - Color Scheme Implementation Guide

**Date:** December 2024  
**Status:** ✅ READY FOR IMPLEMENTATION  
**Theme:** Rich Wine + Gold + Warm Beige (Consistent with SMS Portal)

---

## 📝 **Overview**

This document provides a comprehensive guide for implementing the **Rich Wine + Gold + Warm Beige** color scheme on the Akuru Institute website to ensure perfect brand consistency with the SMS Portal.

---

## 🎯 **Brand Consistency Goals**

- **Unified Brand Identity** - Both platforms share the same professional color palette
- **User Recognition** - Users will immediately recognize the Akuru brand across platforms
- **Professional Cohesion** - Consistent visual language builds trust and credibility
- **Modern Appeal** - Contemporary design that appeals to students and professionals

---

## 🎨 **Color Palette to Implement**

### **Primary Colors**

#### **Rich Wine (Primary Brand Color)**
- **Hex:** `#7C2D37`
- **RGB:** `rgb(124, 45, 55)`
- **HSL:** `hsl(352, 47%, 33%)`
- **Usage:** Headers, primary buttons, navigation, important headings
- **Personality:** Professional, trustworthy, sophisticated

#### **Warm Gold (Secondary Brand Color)**
- **Hex:** `#C9A227`
- **RGB:** `rgb(201, 162, 39)`
- **HSL:** `hsl(45, 68%, 47%)`
- **Usage:** Secondary buttons, accents, highlights, call-to-action elements
- **Personality:** Energetic, premium, attention-grabbing

#### **Warm Beige (Background Color)**
- **Hex:** `#F9F4EE`
- **RGB:** `rgb(249, 244, 238)`
- **HSL:** `hsl(35, 50%, 95%)`
- **Usage:** Main backgrounds, card backgrounds, subtle sections
- **Personality:** Calming, neutral, elegant

---

## 📋 **Implementation Checklist**

### **Phase 1: Core Files Update**

#### **1. Tailwind Configuration**
- **File:** `tailwind.config.js`
- **Action:** Update color palette to match SMS Portal
- **Priority:** High

```javascript
colors: {
  brandMaroon: {
    50: '#FDF7F8',
    100: '#FAECED',
    200: '#F4D8DB',
    300: '#EAB5BA',
    400: '#DD8A92',
    500: '#C85A65',
    600: '#7C2D37', // Primary - Rich Wine
    700: '#6B2630',
    800: '#5A1F28',
    900: '#491821',
  },
  brandGold: {
    50: '#FEFBF3',
    100: '#FDF6E3',
    200: '#FBEDC7',
    300: '#F7E0A0',
    400: '#F0CE69',
    500: '#E8BC3C',
    600: '#C9A227', // Secondary - Warm Gold
    700: '#A8861F',
    800: '#876B19',
    900: '#6B5414',
  },
  brandBeige: {
    50: '#FEFDFB',
    100: '#FDF9F5',
    200: '#F9F4EE', // Main Background
    300: '#F3EBE0',
    400: '#E8DCC9',
    500: '#DCCDAB',
    600: '#C9B388',
    700: '#A89165',
    800: '#85714F',
    900: '#65563D',
  }
}
```

#### **2. CSS Variables**
- **File:** `resources/css/app.css`
- **Action:** Add brand color variables
- **Priority:** High

```css
:root {
  --brand-primary: #7C2D37;
  --brand-secondary: #C9A227;
  --brand-background: #F9F4EE;
  --gradient-primary: linear-gradient(135deg, #7C2D37 0%, #491821 100%);
  --gradient-secondary: linear-gradient(135deg, #C9A227 0%, #6B5414 100%);
  --gradient-accent: linear-gradient(135deg, #7C2D37 0%, #C9A227 100%);
}
```

### **Phase 2: Layout Components**

#### **3. Navigation Bar**
- **Files:** `resources/views/components/public/nav.blade.php`
- **Changes:**
  - Background: Rich Wine gradient
  - Hover states: Gold accents
  - Active states: Rich Wine

#### **4. Footer**
- **Files:** `resources/views/components/public/footer.blade.php`
- **Changes:**
  - Background: Warm Beige
  - Headings: Rich Wine
  - Links: Gold hover states

#### **5. Hero Sections**
- **Files:** `resources/views/public/home.blade.php`
- **Changes:**
  - Background: Rich Wine gradient
  - Text: White with proper contrast
  - Buttons: Gold primary, Rich Wine secondary

### **Phase 3: Page-Specific Updates**

#### **6. Home Page**
- **File:** `resources/views/public/home.blade.php`
- **Updates:**
  - Hero section: Rich Wine gradient
  - Course cards: Gold accents
  - News/Events: Beige backgrounds with Rich Wine headings

#### **7. Course Pages**
- **Files:** `resources/views/public/courses/*.blade.php`
- **Updates:**
  - Course cards: Rich Wine headers
  - Action buttons: Gold primary
  - Descriptions: Beige backgrounds

#### **8. News & Events**
- **Files:** `resources/views/public/news/*.blade.php`, `resources/views/public/events/*.blade.php`
- **Updates:**
  - Article headers: Rich Wine
  - Date badges: Gold
  - Content areas: Beige backgrounds

#### **9. Contact & Admissions**
- **Files:** `resources/views/public/contact/*.blade.php`, `resources/views/public/admissions/*.blade.php`
- **Updates:**
  - Form headers: Rich Wine
  - Submit buttons: Gold
  - Form backgrounds: Beige

---

## 🎨 **Visual Implementation Guide**

### **Header/Navigation**
```css
.navbar {
  background: linear-gradient(135deg, #7C2D37 0%, #491821 100%);
  color: white;
}

.nav-link:hover {
  color: #C9A227;
}

.nav-link.active {
  color: #C9A227;
  border-bottom: 2px solid #C9A227;
}
```

### **Hero Sections**
```css
.hero {
  background: linear-gradient(135deg, #7C2D37 0%, #491821 100%);
  color: white;
}

.hero h1 {
  color: white;
  text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.btn-primary {
  background: #C9A227;
  color: #7C2D37;
  border: none;
}

.btn-secondary {
  background: transparent;
  color: white;
  border: 2px solid #C9A227;
}
```

### **Cards & Content Areas**
```css
.card {
  background: white;
  border: 1px solid #F3EBE0;
  box-shadow: 0 2px 8px rgba(124, 45, 55, 0.1);
}

.card-header {
  background: #F9F4EE;
  color: #7C2D37;
  border-bottom: 2px solid #C9A227;
}

.content-section {
  background: #F9F4EE;
  padding: 2rem;
  border-radius: 8px;
}
```

### **Forms**
```css
.form-control:focus {
  border-color: #7C2D37;
  box-shadow: 0 0 0 0.2rem rgba(124, 45, 55, 0.25);
}

.form-label {
  color: #7C2D37;
  font-weight: 600;
}

.btn-submit {
  background: #C9A227;
  color: #7C2D37;
  border: none;
  padding: 12px 24px;
  border-radius: 6px;
}
```

---

## 📱 **Responsive Considerations**

### **Mobile Navigation**
- Maintain Rich Wine background
- Ensure Gold hover states work on touch
- Keep text readable on small screens

### **Mobile Cards**
- Use full-width cards on mobile
- Maintain color contrast
- Ensure touch targets are accessible

### **Mobile Forms**
- Keep form elements large enough for touch
- Maintain color contrast for readability
- Use appropriate input types

---

## 🌙 **Dark Mode Support**

### **Dark Mode Colors**
```css
[data-theme="dark"] {
  --brand-primary: #7C2D37; /* Unchanged */
  --brand-secondary: #C9A227; /* Unchanged */
  --brand-background: #1a1a1a;
  --text-primary: #ffffff;
  --text-secondary: #e5e5e5;
}
```

### **Dark Mode Implementation**
- Keep Rich Wine and Gold unchanged
- Use dark backgrounds for cards
- Ensure proper contrast ratios
- Test all interactive elements

---

## 🧪 **Testing Checklist**

### **Visual Testing**
- [ ] All pages use consistent colors
- [ ] Hover states work properly
- [ ] Focus states are visible
- [ ] Color contrast meets WCAG standards
- [ ] Mobile responsiveness maintained

### **Cross-Browser Testing**
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile browsers

### **Accessibility Testing**
- [ ] Color contrast ratios ≥ 4.5:1
- [ ] Keyboard navigation works
- [ ] Screen reader compatibility
- [ ] Focus indicators visible

---

## 🚀 **Deployment Steps**

### **1. Pre-Deployment**
- [ ] Backup current color scheme
- [ ] Test in staging environment
- [ ] Review all pages for consistency
- [ ] Check mobile responsiveness

### **2. Deployment**
- [ ] Update Tailwind config
- [ ] Deploy CSS changes
- [ ] Update view files
- [ ] Clear caches

### **3. Post-Deployment**
- [ ] Test all functionality
- [ ] Check for broken styles
- [ ] Verify mobile experience
- [ ] Monitor for issues

---

## 📊 **Expected Results**

### **Brand Consistency**
- ✅ Unified visual identity across platforms
- ✅ Professional, trustworthy appearance
- ✅ Modern, contemporary feel
- ✅ Excellent user recognition

### **User Experience**
- ✅ Improved visual hierarchy
- ✅ Better readability and accessibility
- ✅ Consistent interaction patterns
- ✅ Enhanced professional credibility

### **Technical Benefits**
- ✅ Maintainable color system
- ✅ Consistent component styling
- ✅ Easy future updates
- ✅ Scalable design system

---

## 📞 **Support & Maintenance**

### **Documentation Updates**
- Keep this guide updated with any changes
- Document any custom implementations
- Maintain color usage guidelines

### **Future Considerations**
- Monitor user feedback
- Track conversion metrics
- Plan for seasonal variations
- Consider A/B testing opportunities

---

**Status:** ✅ **READY FOR IMPLEMENTATION**  
**Next Step:** Begin with Phase 1 - Core Files Update

---

*This implementation will ensure perfect brand consistency between the Akuru Institute website and SMS Portal, creating a unified, professional user experience across both platforms.*
