<?php

namespace App\Services;

use Carbon\Carbon;
use Alkoumi\LaravelHijriDate\Hijri;

class IslamicCalendarService
{
    /**
     * Convert Gregorian date to Hijri date (Ummul Qura calendar)
     */
    public static function gregorianToHijri($date = null)
    {
        try {
            if (!$date) {
                $date = Carbon::now();
            } elseif (is_string($date)) {
                $date = Carbon::parse($date);
            }
            
            // Use the Hijri package for accurate conversion
            $hijriDate = Hijri::Date('Y-m-d', $date);
            $hijriParts = explode('-', $hijriDate);
            
            $year = (int) $hijriParts[0];
            $month = (int) $hijriParts[1];
            $day = (int) $hijriParts[2];
            
            return [
                'year' => $year,
                'month' => $month,
                'day' => $day,
                'month_name' => self::getHijriMonthName($month),
                'month_name_arabic' => self::getHijriMonthNameArabic($month),
                'formatted' => $day . ' ' . self::getHijriMonthName($month) . ' ' . $year . ' AH',
                'formatted_arabic' => $day . ' ' . self::getHijriMonthNameArabic($month) . ' ' . $year . ' هـ',
                'hijri_date' => $hijriDate,
            ];
        } catch (\Exception $e) {
            // Fallback to manual calculation
            return self::fallbackGregorianToHijri($date);
        }
    }
    
    /**
     * Fallback method for Hijri conversion
     */
    private static function fallbackGregorianToHijri($date)
    {
        $jd = self::gregorianToJulian($date->year, $date->month, $date->day);
        $hijri = self::julianToHijri($jd);
        
        return [
            'year' => $hijri['year'],
            'month' => $hijri['month'],
            'day' => $hijri['day'],
            'month_name' => self::getHijriMonthName($hijri['month']),
            'month_name_arabic' => self::getHijriMonthNameArabic($hijri['month']),
            'formatted' => $hijri['day'] . ' ' . self::getHijriMonthName($hijri['month']) . ' ' . $hijri['year'] . ' AH',
            'formatted_arabic' => $hijri['day'] . ' ' . self::getHijriMonthNameArabic($hijri['month']) . ' ' . $hijri['year'] . ' هـ',
            'hijri_date' => $hijri['year'] . '-' . $hijri['month'] . '-' . $hijri['day'],
        ];
    }
    
    /**
     * Get current Islamic date
     */
    public static function getCurrentIslamicDate()
    {
        return self::gregorianToHijri();
    }
    
    /**
     * Get Islamic months names
     */
    public static function getHijriMonthName($month)
    {
        $months = [
            1 => 'Muharram',
            2 => 'Safar',
            3 => 'Rabi\' al-awwal',
            4 => 'Rabi\' al-thani',
            5 => 'Jumada al-awwal',
            6 => 'Jumada al-thani',
            7 => 'Rajab',
            8 => 'Sha\'ban',
            9 => 'Ramadan',
            10 => 'Shawwal',
            11 => 'Dhu al-Qi\'dah',
            12 => 'Dhu al-Hijjah',
        ];
        
        return $months[$month] ?? 'Unknown';
    }
    
    /**
     * Get Islamic months names in Arabic
     */
    public static function getHijriMonthNameArabic($month)
    {
        $months = [
            1 => 'محرم',
            2 => 'صفر',
            3 => 'ربيع الأول',
            4 => 'ربيع الثاني',
            5 => 'جمادى الأولى',
            6 => 'جمادى الثانية',
            7 => 'رجب',
            8 => 'شعبان',
            9 => 'رمضان',
            10 => 'شوال',
            11 => 'ذو القعدة',
            12 => 'ذو الحجة',
        ];
        
        return $months[$month] ?? 'غير معروف';
    }
    
    /**
     * Convert Gregorian to Julian day number
     */
    private static function gregorianToJulian($year, $month, $day)
    {
        if ($month <= 2) {
            $year -= 1;
            $month += 12;
        }
        
        $a = intval($year / 100);
        $b = 2 - $a + intval($a / 4);
        
        return intval(365.25 * ($year + 4716)) + intval(30.6001 * ($month + 1)) + $day + $b - 1524.5;
    }
    
    /**
     * Convert Julian day number to Hijri date
     */
    private static function julianToHijri($jd)
    {
        $jd = $jd - 1948439.5; // Adjust for Hijri epoch
        $year = intval((30 * $jd + 10646) / 10631);
        $month = intval((11 * $jd + 330) / 325);
        $day = $jd - intval((325 * $month - 320) / 11);
        
        if ($month > 12) {
            $year += 1;
            $month -= 12;
        }
        
        return [
            'year' => $year,
            'month' => $month,
            'day' => intval($day),
        ];
    }
    
    /**
     * Get prayer times for a given date and location
     */
    public static function getPrayerTimes($date = null, $latitude = 4.1755, $longitude = 73.5093) // Malé coordinates
    {
        if (!$date) {
            $date = Carbon::now();
        } elseif (is_string($date)) {
            $date = Carbon::parse($date);
        }
        
        // This is a simplified calculation - for production, use a proper prayer times library
        $times = [
            'fajr' => $date->copy()->setTime(5, 30),
            'dhuhr' => $date->copy()->setTime(12, 15),
            'asr' => $date->copy()->setTime(15, 30),
            'maghrib' => $date->copy()->setTime(18, 15),
            'isha' => $date->copy()->setTime(19, 30),
        ];
        
        return $times;
    }
    
    /**
     * Check if current time is within prayer time
     */
    public static function getCurrentPrayerTime()
    {
        $now = Carbon::now();
        $prayerTimes = self::getPrayerTimes();
        
        foreach ($prayerTimes as $prayer => $time) {
            if ($now->between($time, $time->copy()->addMinutes(30))) {
                return [
                    'prayer' => $prayer,
                    'time' => $time,
                    'is_prayer_time' => true,
                ];
            }
        }
        
        return [
            'prayer' => null,
            'time' => null,
            'is_prayer_time' => false,
        ];
    }
    
    /**
     * Convert Hijri date to Gregorian date
     */
    public static function hijriToGregorian($hijriDate)
    {
        try {
            return Hijri::Date('Y-m-d', $hijriDate, 'gregorian');
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Get special Islamic days for current date
     */
    public static function getSpecialIslamicDays()
    {
        $currentHijri = self::getCurrentIslamicDate();
        $currentMonth = $currentHijri['month_name'];
        $currentDay = $currentHijri['day'];
        
        $specialDays = [];
        
        // Check for special days in current month
        if ($currentMonth === 'Muharram' && $currentDay === 1) {
            $specialDays[] = 'Islamic New Year';
        } elseif ($currentMonth === 'Muharram' && $currentDay === 10) {
            $specialDays[] = 'Day of Ashura';
        } elseif ($currentMonth === 'Rabi\' al-awwal' && $currentDay === 12) {
            $specialDays[] = 'Prophet Muhammad\'s Birthday';
        } elseif ($currentMonth === 'Rajab' && $currentDay === 27) {
            $specialDays[] = 'Isra and Mi\'raj';
        } elseif ($currentMonth === 'Ramadan' && $currentDay === 1) {
            $specialDays[] = 'First Day of Ramadan';
        } elseif ($currentMonth === 'Ramadan' && $currentDay >= 27) {
            $specialDays[] = 'Laylat al-Qadr (Night of Power)';
        } elseif ($currentMonth === 'Shawwal' && $currentDay === 1) {
            $specialDays[] = 'Eid al-Fitr';
        } elseif ($currentMonth === 'Dhu al-Hijjah' && $currentDay === 9) {
            $specialDays[] = 'Day of Arafah';
        } elseif ($currentMonth === 'Dhu al-Hijjah' && $currentDay === 10) {
            $specialDays[] = 'Eid al-Adha';
        }
        
        return $specialDays;
    }
    
    /**
     * Get Islamic calendar months with their Arabic names
     */
    public static function getIslamicMonths()
    {
        return [
            1 => ['en' => 'Muharram', 'ar' => 'محرم'],
            2 => ['en' => 'Safar', 'ar' => 'صفر'],
            3 => ['en' => 'Rabi\' al-awwal', 'ar' => 'ربيع الأول'],
            4 => ['en' => 'Rabi\' al-thani', 'ar' => 'ربيع الثاني'],
            5 => ['en' => 'Jumada al-awwal', 'ar' => 'جمادى الأولى'],
            6 => ['en' => 'Jumada al-thani', 'ar' => 'جمادى الثانية'],
            7 => ['en' => 'Rajab', 'ar' => 'رجب'],
            8 => ['en' => 'Sha\'ban', 'ar' => 'شعبان'],
            9 => ['en' => 'Ramadan', 'ar' => 'رمضان'],
            10 => ['en' => 'Shawwal', 'ar' => 'شوال'],
            11 => ['en' => 'Dhu al-Qi\'dah', 'ar' => 'ذو القعدة'],
            12 => ['en' => 'Dhu al-Hijjah', 'ar' => 'ذو الحجة'],
        ];
    }
}
